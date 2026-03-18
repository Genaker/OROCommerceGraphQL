<?php

namespace Genaker\Bundle\GraphQLBundle\Schema;

use Genaker\Bundle\GraphQLBundle\Schema\Type\MutationType;
use Genaker\Bundle\GraphQLBundle\Schema\Type\ProductType;
use Genaker\Bundle\GraphQLBundle\Schema\Type\QueryType;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

/**
 * Assembles the complete GraphQL schema.
 *
 * Wired as a Symfony service so all dependencies (Doctrine, types, resolvers)
 * are injected by the DI container.
 *
 * NOTE: The schema definition is also documented in SDL format at:
 *   Resources/schema.graphql
 *
 * The SDL file is for:
 *   - IDE autocompletion (GraphQL extensions)
 *   - Schema documentation
 *   - Schema validation tools
 *   - Code generation tools
 *
 * The actual schema is built programmatically here to maintain tight integration
 * with Symfony DI, Doctrine ORM, and custom resolver logic.
 */
class SchemaFactory
{
    public function __construct(
        private readonly ProductType $productType,
        private readonly QueryType   $queryType,
        private readonly MutationType $mutationType
    ) {
    }

    public function createSchema(): Schema
    {
        return new Schema(
            SchemaConfig::create()
                ->setQuery($this->queryType)
                ->setMutation($this->mutationType)
                ->setTypes([$this->productType])
        );
    }
}
