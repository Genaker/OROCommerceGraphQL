<?php

// Run: bin/phpunit -c phpunit-graphql.xml.dist --testdox

namespace Genaker\Bundle\GraphQLBundle\Tests\Integration;

use Egerdau\Bundle\ShippingCartBundle\Tests\Integration\DatabaseTestTrait;
use Egerdau\Bundle\ShippingCartBundle\Tests\Integration\HttpTestCase;

/**
 * Integration tests for the GenakerGraphQLBundle endpoints.
 *
 * Endpoints under test:
 *   GET  /admin/api/graphql  — schema introspection / health-check
 *   POST /admin/api/graphql  — execute a GraphQL query
 *
 * Auth: OAuth2 Bearer token — obtained via HttpTestCase::getOAuthBearerToken().
 * Requires: running Symfony dev server (default https://localhost:8000).
 *
 * All product-level tests are forgiving: when the database contains no
 * products they assert on structure not specific data.
 */
class GraphQLIntegrationTest extends HttpTestCase
{
    use DatabaseTestTrait;

    private const ENDPOINT = '/admin/api/graphql';

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDbFromEnv();
        $this->token = $this->getOAuthBearerToken();
    }

    // ── Introspection (GET) ───────────────────────────────────────────────────

    /**
     * GET /admin/api/graphql returns 200 with a schema inventory payload.
     */
    public function testIntrospect_returnsSchemaInventory(): void
    {
        $response = $this->get(self::ENDPOINT, [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);

        $this->assertSame('ok', $body['status'] ?? null, 'Introspect must return status: ok');
        $this->assertArrayHasKey('queries', $body, 'Introspect must list available queries');
        $this->assertIsArray($body['queries']);

        // GenakerGraphQLBundle-specific fields
        $this->assertSame('GenakerGraphQLBundle', $body['bundle'] ?? null);
        $this->assertSame('1.0.0', $body['version'] ?? null);

        // Schema must expose the two root fields
        $this->assertArrayHasKey('product',  $body['queries'], 'Schema must expose product field');
        $this->assertArrayHasKey('products', $body['queries'], 'Schema must expose products field');
    }

    /**
     * GET /admin/api/graphql without Bearer token must be rejected (401 or 403).
     */
    public function testIntrospect_unauthenticated_returns401or403(): void
    {
        $response = $this->get(self::ENDPOINT);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            'Unauthenticated GET must be rejected. Got HTTP ' . $response->getStatusCode()
        );
    }

    // ── Authentication guard (POST) ───────────────────────────────────────────

    /**
     * POST /admin/api/graphql without Bearer token must be rejected (401 or 403).
     */
    public function testExecute_unauthenticated_returns401or403(): void
    {
        $response = $this->post(self::ENDPOINT, ['query' => '{ products { totalCount } }']);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            'Unauthenticated POST must be rejected. Got HTTP ' . $response->getStatusCode()
        );
    }

    // ── Bad request guards (POST) ─────────────────────────────────────────────

    /**
     * POST with syntactically invalid JSON must return 400.
     * Oro intercepts the malformed body before the controller and returns its
     * standard API error envelope: [{title, detail}] (not GraphQL's {errors}).
     */
    public function testExecute_malformedJson_returns400(): void
    {
        $response = $this->doRequest('POST', self::ENDPOINT, '{not valid json', [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json',
        ]);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);

        // Oro returns an array of error objects [{title, detail}] for body-parse failures.
        // The controller never runs so there is no GraphQL {errors} envelope.
        if (\is_array($body) && isset($body[0])) {
            // Oro standard envelope: [{title: "...", detail: "..."}]
            $this->assertArrayHasKey('title', $body[0]);
            $this->assertStringContainsStringIgnoringCase('bad request', $body[0]['title'] ?? '');
        } elseif (\is_array($body) && isset($body['errors'])) {
            // GraphQL controller envelope (future-proof if Oro is bypassed)
            $this->assertStringContainsStringIgnoringCase('malformed', $body['errors'][0]['message'] ?? '');
        } else {
            $this->fail('Unexpected 400 response body: ' . $response->getContent());
        }
    }

    /**
     * POST with valid JSON but no "query" key must return 400.
     */
    public function testExecute_missingQueryField_returns400(): void
    {
        $response = $this->post(self::ENDPOINT, ['variables' => []], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertNotEmpty($body['errors']);
    }

    /**
     * POST with a syntactically invalid GraphQL query must return HTTP 200
     * (GraphQL spec §7.1) with an errors array in the body.
     */
    public function testExecute_invalidGraphQLSyntax_returns200WithErrors(): void
    {
        $response = $this->post(self::ENDPOINT, ['query' => '{ this is not graphql !!!'], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body, 'Invalid GQL must return errors array');
        $this->assertNotEmpty($body['errors']);
    }

    // ── Content-Type ──────────────────────────────────────────────────────────

    /**
     * Successful responses must carry Content-Type: application/json.
     */
    public function testExecute_responseContentType_isJson(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 1) { totalCount } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('application/json', $contentType);
    }

    // ── products query ────────────────────────────────────────────────────────

    /**
     * products query must return a valid ProductConnection envelope.
     */
    public function testExecute_productsQuery_returnsConnectionShape(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 5) { totalCount hasNextPage hasPreviousPage startCursor endCursor items { id sku status } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('errors', $body, 'products query must not return errors: ' . json_encode($body['errors'] ?? []));
        $this->assertArrayHasKey('data', $body);

        $conn = $body['data']['products'] ?? null;
        $this->assertIsArray($conn);
        $this->assertArrayHasKey('totalCount',      $conn);
        $this->assertArrayHasKey('hasNextPage',     $conn);
        $this->assertArrayHasKey('hasPreviousPage', $conn);
        $this->assertArrayHasKey('items',           $conn);
        $this->assertIsInt($conn['totalCount']);
        $this->assertIsBool($conn['hasNextPage']);
        $this->assertIsBool($conn['hasPreviousPage']);
        $this->assertIsArray($conn['items']);
    }

    /**
     * products with limit:1 and no filters must cap the result at 1 item.
     */
    public function testExecute_productsQuery_limitIsRespected(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 1) { totalCount items { id sku } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body  = json_decode($response->getContent(), true);
        $items = $body['data']['products']['items'] ?? null;
        $this->assertIsArray($items);
        $this->assertLessThanOrEqual(1, count($items), 'limit:1 must return at most 1 item');
    }

    /**
     * products filtered by status:"enabled" must only return enabled products.
     */
    public function testExecute_productsQuery_statusFilter_returnsOnlyEnabled(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(status: "enabled", limit: 10) { items { id status } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body  = json_decode($response->getContent(), true);
        $items = $body['data']['products']['items'] ?? [];

        foreach ($items as $item) {
            $this->assertSame('enabled', $item['status'], 'status filter must only return enabled products');
        }
    }

    /**
     * products with offset:0 then offset:1 must shift results by one row.
     */
    public function testExecute_productsQuery_offsetPagination(): void
    {
        // Page 0
        $r0 = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 2, offset: 0) { totalCount items { id } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );
        $b0    = json_decode($r0->getContent(), true);
        $total = $b0['data']['products']['totalCount'] ?? 0;

        if ($total < 2) {
            $this->markTestSkipped('Need at least 2 products for offset pagination test.');
        }

        $ids0 = array_column($b0['data']['products']['items'], 'id');

        // Page 1 (shift by 1)
        $r1  = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 2, offset: 1) { items { id } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );
        $b1  = json_decode($r1->getContent(), true);
        $ids1 = array_column($b1['data']['products']['items'], 'id');

        $this->assertSame($ids0[1], $ids1[0], 'Offset by 1 must shift first result');
    }

    /**
     * Cursor-based pagination: endCursor from page 1 used as `after` on page 2
     * must produce a non-overlapping result set.
     */
    public function testExecute_productsQuery_cursorPagination(): void
    {
        // First page
        $r1  = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 1) { totalCount endCursor items { id } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );
        $b1     = json_decode($r1->getContent(), true);
        $total  = $b1['data']['products']['totalCount'] ?? 0;
        $cursor = $b1['data']['products']['endCursor'] ?? null;
        $ids1   = array_column($b1['data']['products']['items'] ?? [], 'id');

        if ($total < 2 || $cursor === null) {
            $this->markTestSkipped('Need at least 2 products for cursor pagination test.');
        }

        // Second page using after cursor
        $r2   = $this->post(
            self::ENDPOINT,
            ['query' => sprintf('{ products(limit: 1, after: "%s") { items { id } hasPreviousPage } }', $cursor)],
            ['Authorization' => 'Bearer ' . $this->token]
        );
        $b2   = json_decode($r2->getContent(), true);
        $ids2 = array_column($b2['data']['products']['items'] ?? [], 'id');

        // No overlap between pages
        $this->assertEmpty(
            array_intersect($ids1, $ids2),
            'Cursor pages must not overlap'
        );

        // hasPreviousPage must be true on the second page
        $this->assertTrue(
            $b2['data']['products']['hasPreviousPage'] ?? false,
            'Page 2 must report hasPreviousPage: true'
        );
    }

    // ── product(id:) query ────────────────────────────────────────────────────

    /**
     * product(id: 999999999) for a non-existent ID must return data.product: null (not an error).
     */
    public function testExecute_productById_nonExistentId_returnsNull(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => '{ product(id: 999999999) { id sku } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('errors', $body, json_encode($body['errors'] ?? []));
        $this->assertNull($body['data']['product'], 'Non-existent ID must resolve to null');
    }

    /**
     * product(id: N) for an existing product must return all expected fields.
     */
    public function testExecute_productById_existingId_returnsAllFields(): void
    {
        // Fetch the first available product ID via products query
        $listResponse = $this->post(
            self::ENDPOINT,
            ['query' => '{ products(limit: 1) { items { id } } }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );
        $listBody = json_decode($listResponse->getContent(), true);
        $firstId  = $listBody['data']['products']['items'][0]['id'] ?? null;

        if ($firstId === null) {
            $this->markTestSkipped('No products in the database; skipping product-by-id test.');
        }

        $response = $this->post(
            self::ENDPOINT,
            ['query' => sprintf('{ product(id: %d) { id sku name status type featured } }', $firstId)],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body    = json_decode($response->getContent(), true);
        $product = $body['data']['product'] ?? null;

        $this->assertIsArray($product, 'product must not be null for an existing id');
        $this->assertSame($firstId, $product['id']);
        $this->assertArrayHasKey('sku',      $product);
        $this->assertArrayHasKey('name',     $product);
        $this->assertArrayHasKey('status',   $product);
        $this->assertArrayHasKey('type',     $product);
        $this->assertArrayHasKey('featured', $product);
    }

    // ── application/graphql Content-Type ─────────────────────────────────────

    /**
     * POST with Content-Type: application/graphql (raw query body) must be accepted.
     */
    public function testExecute_applicationGraphqlContentType_isAccepted(): void
    {
        $response = $this->doRequest(
            'POST',
            self::ENDPOINT,
            '{ products(limit: 1) { totalCount } }',
            [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/graphql',
            ]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('errors', $body, json_encode($body['errors'] ?? []));
        $this->assertArrayHasKey('data', $body);
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /**
     * testMutation without arguments must return true.
     */
    public function testExecute_testMutation_noArgs_returnsTrue(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => 'mutation { testMutation }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('errors', $body, json_encode($body['errors'] ?? []));
        $this->assertTrue($body['data']['testMutation'], 'testMutation must return true');
    }

    /**
     * testMutation with message argument must ignore the message and return true.
     */
    public function testExecute_testMutation_withMessage_returnsTrue(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => 'mutation { testMutation(message: "Hello GraphQL") }'],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('errors', $body, json_encode($body['errors'] ?? []));
        $this->assertTrue($body['data']['testMutation'], 'testMutation must return true even with message');
    }

    /**
     * testMutation without authentication must return 401 or 403.
     */
    public function testExecute_testMutation_unauthenticated_returns401or403(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            ['query' => 'mutation { testMutation }'],
            [] // no Authorization header
        );

        $this->assertThat(
            $response->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(401),
                $this->equalTo(403)
            ),
            'Unauthenticated mutation must return 401 or 403'
        );
    }
}
