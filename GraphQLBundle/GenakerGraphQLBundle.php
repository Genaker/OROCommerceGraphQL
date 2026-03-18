<?php

namespace Genaker\Bundle\GraphQLBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * GenakerGraphQLBundle – exposes an OroCommerce GraphQL endpoint.
 *
 * Endpoint:  POST /graphql
 * Library:   webonyx/graphql-php (require via composer)
 * Security:  OAuth2 Bearer token (api_secured firewall)
 *
 * Supported root queries
 * ─────────────────────
 *  product(id: Int!): Product
 *  products(sku: String, status: String, featured: Boolean, limit: Int, offset: Int): [Product]
 */
class GenakerGraphQLBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
