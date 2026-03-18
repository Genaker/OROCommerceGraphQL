<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Resolver;

/**
 * Resolves GraphQL mutation operations.
 *
 * testMutation → true (always)
 */
class MutationResolver
{
    /**
     * Fake mutation that always returns true.
     * Used for testing mutation infrastructure and client implementations.
     *
     * @param mixed                 $objectValue Unused
     * @param array<string, mixed>  $args        { message?: string }
     * @return bool Always true
     */
    public function resolveTestMutation(mixed $objectValue, array $args): bool
    {
        // Log the mutation invocation if monitoring is needed
        // \error_log('testMutation called with message: ' . ($args['message'] ?? 'none'));

        return true;
    }
}
