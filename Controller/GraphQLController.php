<?php

namespace Genaker\Bundle\GraphQLBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Product GraphQL endpoint.
 *
 * Routes (defined in Resources/config/oro/routing.yml)
 * ─────────────────────────────────────────────────────
 *   POST /admin/api/graphql   – execute a GraphQL query
 *   GET  /admin/api/graphql   – health-check / schema inventory
 *
 * Security
 * ────────
 * Covered by the `api_secured` firewall via the %oro_api.rest.prefix% path.
 * Authenticates with an OAuth2 Bearer token.
 *
 * Request format (POST, Content-Type: application/json)
 * ─────────────────────────────────────────────────────
 * {
 *   "query":         "{ products(status: \"enabled\", limit: 5) { id sku name } }",
 *   "variables":     {},    // optional
 *   "operationName": null   // optional
 * }
 *
 * All GraphQL infrastructure (parsing, execution, debug flags, error wrapping)
 * lives in AbstractGraphQLController — nothing technical leaks into this class.
 */
class GraphQLController extends AbstractGraphQLController
{
    // ── POST /admin/api/graphql ───────────────────────────────────────────────

    public function executeAction(Request $request): JsonResponse
    {
        return $this->handleQuery($request);
    }

    // ── GET /admin/api/graphql ────────────────────────────────────────────────

    public function introspectAction(): JsonResponse
    {
        return $this->handleIntrospect();
    }

    // ── Hook: customise the health-check payload ──────────────────────────────

    /**
     * @param array<string, string> $queryFields
     * @return array<string, mixed>
     */
    protected function buildIntrospectPayload(array $queryFields): array
    {
        return [
            ...parent::buildIntrospectPayload($queryFields),
            'bundle'  => 'GenakerGraphQLBundle',
            'version' => '1.0.0',
        ];
    }
}
