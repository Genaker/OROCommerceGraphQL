<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ProductBundle\Entity\Product;

/**
 * Fetches Oro Product records from the database.
 *
 * resolveProduct  → single Product or null
 * resolveProducts → ProductConnection array:
 *   [
 *     'items'           => Product[],
 *     'totalCount'      => int,   — total rows matching filters (no limit/offset)
 *     'hasNextPage'     => bool,
 *     'hasPreviousPage' => bool,
 *     'startCursor'     => string|null,  — base64("offset:<first-item-offset>")
 *     'endCursor'       => string|null,  — base64("offset:<next-page-start>")
 *   ]
 *
 * Cursor encoding: base64_encode("offset:{$offset}")
 * Pass endCursor as the `after` argument to fetch the next page.
 */
class ProductResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    // ── Single product by PK ─────────────────────────────────────────────────

    public function resolveProduct(mixed $root, array $args): ?Product
    {
        return $this->em->getRepository(Product::class)->find($args['id']);
    }

    // ── Paginated product list — returns ProductConnection data ───────────────

    /**
     * @return array{items: Product[], totalCount: int, hasNextPage: bool,
     *               hasPreviousPage: bool, startCursor: string|null, endCursor: string|null}
     */
    public function resolveProducts(mixed $root, array $args): array
    {
        $limit  = min((int) ($args['limit'] ?? 20), 100);   // hard cap at 100
        $offset = $this->decodeOffset($args);

        // Base QueryBuilder with all filter conditions applied (no limit/offset yet)
        $baseQb = $this->buildFilteredQb($args);

        // ── Count query (same filters, no pagination) ─────────────────────────
        $totalCount = (int) (clone $baseQb)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // ── Data query ────────────────────────────────────────────────────────
        $items = (clone $baseQb)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $count          = count($items);
        $hasNextPage    = ($offset + $limit) < $totalCount;
        $hasPreviousPage = $offset > 0;

        // Cursors: encode the offset so clients can navigate without knowing internals
        $startCursor = $count > 0 ? $this->encodeCursor($offset)           : null;
        $endCursor   = $count > 0 ? $this->encodeCursor($offset + $count)  : null;

        return [
            'items'           => $items,
            'totalCount'      => $totalCount,
            'hasNextPage'     => $hasNextPage,
            'hasPreviousPage' => $hasPreviousPage,
            'startCursor'     => $startCursor,
            'endCursor'       => $endCursor,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build a QueryBuilder with all filter args applied but no LIMIT / OFFSET.
     * Clone it once for COUNT and once for the data fetch.
     */
    private function buildFilteredQb(array $args): QueryBuilder
    {
        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('p');

        if (!empty($args['sku'])) {
            $qb->andWhere('p.sku = :sku')->setParameter('sku', $args['sku']);
        }
        if (!empty($args['status'])) {
            $qb->andWhere('p.status = :status')->setParameter('status', $args['status']);
        }
        if (isset($args['featured'])) {
            $qb->andWhere('p.featured = :featured')->setParameter('featured', (bool) $args['featured']);
        }
        if (!empty($args['type'])) {
            $qb->andWhere('p.type = :type')->setParameter('type', $args['type']);
        }

        return $qb;
    }

    /**
     * Resolve the page offset:
     *   1. Decode the `after` cursor (takes priority)
     *   2. Fall back to the raw `offset` arg
     *   3. Default to 0
     */
    private function decodeOffset(array $args): int
    {
        if (!empty($args['after'])) {
            $decoded = base64_decode($args['after'], strict: true);
            if ($decoded !== false && str_starts_with($decoded, 'offset:')) {
                return max(0, (int) substr($decoded, 7));
            }
        }

        return max(0, (int) ($args['offset'] ?? 0));
    }

    /** Encode an integer offset into an opaque cursor string. */
    private function encodeCursor(int $offset): string
    {
        return base64_encode('offset:' . $offset);
    }
}
