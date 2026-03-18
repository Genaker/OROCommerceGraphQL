<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Type;

use Genaker\Bundle\GraphQLBundle\Schema\Resolver\MutationResolver;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Root Mutation type – entry-point for all GraphQL write operations.
 *
 * Available mutations:
 *
 *   testMutation(message: String!): MutationResult
 *     Fake mutation that always returns true with a supplied message.
 *     Used for testing mutation infrastructure and client implementations.
 */
class MutationType extends ObjectType
{
    public function __construct(
        MutationResolver $mutationResolver,
    ) {
        parent::__construct([
            'name'   => 'Mutation',
            'fields' => [

                // ── testMutation(message: String!): MutationResult ─────────────
                'testMutation' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'Fake mutation that always returns true. Used for testing mutation infrastructure.',
                    'args'        => [
                        'message' => [
                            'type'        => Type::string(),
                            'description' => 'Optional message to echo in response.',
                        ],
                    ],
                    'resolve' => [$mutationResolver, 'resolveTestMutation'],
                ],
            ],
        ]);
    }
}
