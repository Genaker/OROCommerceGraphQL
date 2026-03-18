<?php

namespace Genaker\Bundle\GraphQLBundle\Controller;

use Genaker\Bundle\GraphQLBundle\Schema\SchemaFactory;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GraphQL infrastructure layer.
 *
 * Handles everything that is NOT business logic:
 *   – request body parsing (JSON / application/graphql / form-urlencoded)
 *   – query execution via webonyx/graphql-php
 *   – debug-flag toggling based on %kernel.debug%
 *   – uniform error envelope on unexpected exceptions
 *   – schema introspection response
 *
 * Concrete controllers extend this class and call the protected helpers.
 * Their action methods stay 1-liners.
 */
abstract class AbstractGraphQLController
{
    public function __construct(
        protected readonly SchemaFactory $schemaFactory,
        protected readonly bool          $debugMode = false,
    ) {
    }

    // ── Public entry points (called by concrete actions) ─────────────────────

    /**
     * Parse, validate, and execute the GraphQL query from an HTTP request.
     *
     * Always returns HTTP 200 (GraphQL spec §7.1): transport success is
     * orthogonal to query-level errors which live in the response body.
     */
    final protected function handleQuery(Request $request): JsonResponse
    {
        try {
            ['query' => $queryString, 'variables' => $variables, 'operationName' => $operationName]
                = $this->parseRequestBody($request);
        } catch (\JsonException $e) {
            return $this->errorResponse('Malformed JSON body: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if (empty($queryString)) {
            return $this->errorResponse('Missing "query" in request body.', Response::HTTP_BAD_REQUEST);
        }

        return $this->execute(
            schema:        $this->schemaFactory->createSchema(),
            queryString:   $queryString,
            variables:     $variables,
            operationName: $operationName,
        );
    }

    /**
     * Return a self-describing health-check / schema inventory response.
     * Safe to call on GET — does not mutate anything.
     */
    final protected function handleIntrospect(): JsonResponse
    {
        $schema     = $this->schemaFactory->createSchema();
        $queryFields = [];

        foreach ($schema->getQueryType()->getFields() as $name => $field) {
            $queryFields[$name] = (string) $field->getType();
        }

        return new JsonResponse($this->buildIntrospectPayload($queryFields));
    }

    // ── Extension points ──────────────────────────────────────────────────────

    /**
     * Override to customise the health-check payload (e.g. add version, env).
     *
     * @param array<string, string> $queryFields  name → type-string map
     * @return array<string, mixed>
     */
    protected function buildIntrospectPayload(array $queryFields): array
    {
        return [
            'status'  => 'ok',
            'queries' => $queryFields,
            'tip'     => 'POST application/json {"query":"{ … }"} with Bearer token.',
        ];
    }

    // ── Private infrastructure ────────────────────────────────────────────────

    /**
     * Parse three standard GraphQL request fields from any supported
     * Content-Type.
     *
     * @return array{query: string|null, variables: mixed, operationName: string|null}
     * @throws \JsonException on malformed JSON
     */
    private function parseRequestBody(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type', '');

        $body = match (true) {
            str_contains($contentType, 'application/json')    => json_decode(
                $request->getContent(), true, 512, \JSON_THROW_ON_ERROR
            ),
            str_contains($contentType, 'application/graphql') => ['query' => $request->getContent()],
            default                                           => $request->request->all() ?: [],
        };

        return [
            'query'         => $body['query']         ?? null,
            'variables'     => $body['variables']      ?? null,
            'operationName' => $body['operationName']  ?? null,
        ];
    }

    /**
     * Run the GraphQL query against the schema, return a JSON response.
     * Catches any unexpected \Throwable so the endpoint never leaks a 500.
     */
    private function execute(
        Schema  $schema,
        string  $queryString,
        mixed   $variables,
        ?string $operationName,
    ): JsonResponse {
        try {
            $result = GraphQL::executeQuery(
                schema:         $schema,
                source:         $queryString,
                variableValues: \is_array($variables) ? $variables : null,
                operationName:  $operationName,
            );

            $data = $result->toArray($this->resolveDebugFlags());

        } catch (\Throwable $e) {
            $message = $this->debugMode ? $e->getMessage() : 'Internal server error.';
            $data    = ['errors' => [['message' => $message]]];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /** Map the boolean $debugMode flag to webonyx DebugFlag bitmask. */
    private function resolveDebugFlags(): int
    {
        return $this->debugMode
            ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
            : DebugFlag::NONE;
    }

    /** Uniform error envelope for pre-execution HTTP-level failures. */
    private function errorResponse(string $message, int $httpStatus): JsonResponse
    {
        return new JsonResponse(
            ['errors' => [['message' => $message]]],
            $httpStatus,
        );
    }
}
