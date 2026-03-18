<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Type;

use Genaker\Bundle\GraphQLBundle\Schema\Resolver\ProductResolver;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Root Query type – entry-point for all GraphQL read operations.
 *
 * Available queries:
 *
 *   product(id: Int!): Product
 *
 *   products(
 *     sku:      String
 *     status:   String   # "enabled" | "disabled"
 *     type:     String   # "simple" | "configurable" | "kit"
 *     featured: Boolean
 *     limit:    Int      # default 20, max 100
 *     offset:   Int      # default 0  (ignored when `after` is provided)
 *     after:    String   # opaque cursor from previous page's endCursor
 *   ): ProductConnection
 *
 * ProductConnection returns: items, totalCount, hasNextPage, hasPreviousPage,
 *   startCursor, endCursor
 */
class QueryType extends ObjectType
{
    public function __construct(
        ProductType           $productType,
        ProductConnectionType $productConnectionType,
        ProductResolver       $productResolver,
    ) {
        parent::__construct([
            'name'   => 'Query',
            'fields' => [

                // ── product(id: Int!): Product ────────────────────────────────
                'product' => [
                    'type'        => $productType,
                    'description' => 'Fetch a single product by its primary key.',
                    'args'        => [
                        'id' => ['type' => Type::nonNull(Type::int()), 'description' => 'Product ID (PK).'],
                    ],
                    'resolve' => [$productResolver, 'resolveProduct'],
                ],

                // ── products([filters]): ProductConnection ────────────────────
                'products' => [
                    'type'        => Type::nonNull($productConnectionType),
                    'description' => 'Paginated product list with totalCount and cursor navigation.',
                    'args'        => [
                        'sku'      => ['type' => Type::string(),  'description' => 'Filter by exact SKU.'],
                        'status'   => ['type' => Type::string(),  'description' => '"enabled" or "disabled".'],
                        'type'     => ['type' => Type::string(),  'description' => '"simple", "configurable", or "kit".'],
                        'featured' => ['type' => Type::boolean(), 'description' => 'Filter by featured flag.'],
                        'limit'    => ['type' => Type::int(),     'description' => 'Max results per page (default 20, max 100).'],
                        'offset'   => ['type' => Type::int(),     'description' => 'Page offset — ignored when `after` cursor is provided.'],
                        'after'    => ['type' => Type::string(),  'description' => 'Opaque cursor from endCursor of a previous response.'],
                    ],
                    'resolve' => [$productResolver, 'resolveProducts'],
                ],
            ],
        ]);
    }
}
