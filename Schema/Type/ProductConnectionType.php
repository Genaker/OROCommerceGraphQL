<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * GraphQL Connection wrapper for paginated product lists.
 *
 * Follows the Relay Connection spec (simplified, offset-based cursors):
 *
 *   type ProductConnection {
 *     items:           [Product!]!   — the page of products
 *     totalCount:      Int!          — total matching rows (ignoring limit/offset)
 *     hasNextPage:     Boolean!      — true when more pages exist after this one
 *     hasPreviousPage: Boolean!      — true when offset > 0
 *     startCursor:     String        — opaque cursor for the first item on this page
 *     endCursor:       String        — opaque cursor to pass as `after` for next page
 *   }
 *
 * Cursor encoding: base64("offset:<N>")
 * Use endCursor as the `after` argument on the next request.
 *
 * Example query:
 *   {
 *     products(status: "enabled", limit: 10) {
 *       totalCount
 *       hasNextPage
 *       endCursor
 *       items { id sku name }
 *     }
 *   }
 *
 * Second page (cursor-based):
 *   {
 *     products(status: "enabled", limit: 10, after: "<endCursor>") {
 *       totalCount hasNextPage endCursor
 *       items { id sku name }
 *     }
 *   }
 */
class ProductConnectionType extends ObjectType
{
    public function __construct(ProductType $productType)
    {
        parent::__construct([
            'name'        => 'ProductConnection',
            'description' => 'A paginated list of products with metadata.',
            'fields'      => [

                'items' => [
                    'type'        => Type::nonNull(Type::listOf(Type::nonNull($productType))),
                    'description' => 'The products on this page.',
                    'resolve'     => static fn (array $conn) => $conn['items'],
                ],

                'totalCount' => [
                    'type'        => Type::nonNull(Type::int()),
                    'description' => 'Total number of products matching the applied filters (ignores limit/offset).',
                    'resolve'     => static fn (array $conn) => $conn['totalCount'],
                ],

                'hasNextPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'True when there are more products after this page.',
                    'resolve'     => static fn (array $conn) => $conn['hasNextPage'],
                ],

                'hasPreviousPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'True when the current page is not the first page.',
                    'resolve'     => static fn (array $conn) => $conn['hasPreviousPage'],
                ],

                'startCursor' => [
                    'type'        => Type::string(),
                    'description' => 'Opaque cursor pointing to the first item on this page.',
                    'resolve'     => static fn (array $conn) => $conn['startCursor'],
                ],

                'endCursor' => [
                    'type'        => Type::string(),
                    'description' => 'Opaque cursor to pass as `after` to fetch the next page.',
                    'resolve'     => static fn (array $conn) => $conn['endCursor'],
                ],
            ],
        ]);
    }
}
