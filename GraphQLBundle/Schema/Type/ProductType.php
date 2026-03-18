<?php

namespace Genaker\Bundle\GraphQLBundle\Schema\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * GraphQL Product type.
 *
 * Maps to Oro\Bundle\ProductBundle\Entity\Product.
 *
 * Fields exposed:
 *   id          Int     – PK
 *   sku         String  – product SKU (uppercase variant available too)
 *   name        String  – denormalised default name
 *   status      String  – "enabled" | "disabled"
 *   type        String  – "simple" | "configurable" | "kit"
 *   featured    Boolean – whether the product is featured
 *   newArrival  Boolean – new-arrival flag
 *   createdAt   String  – ISO-8601 datetime string
 *   updatedAt   String  – ISO-8601 datetime string
 */
class ProductType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name'        => 'Product',
            'description' => 'An OroCommerce product record.',
            'fields'      => static fn (): array => [
                'id' => [
                    'type'        => Type::nonNull(Type::int()),
                    'description' => 'Primary key.',
                    'resolve'     => static fn ($p) => $p->getId(),
                ],
                'sku' => [
                    'type'        => Type::string(),
                    'description' => 'Product SKU.',
                    'resolve'     => static fn ($p) => $p->getSku(),
                ],
                'name' => [
                    'type'        => Type::string(),
                    'description' => 'Default (denormalised) product name.',
                    'resolve'     => static fn ($p) => $p->getDenormalizedDefaultName(),
                ],
                'status' => [
                    'type'        => Type::string(),
                    'description' => '"enabled" or "disabled".',
                    'resolve'     => static fn ($p) => $p->getStatus(),
                ],
                'type' => [
                    'type'        => Type::string(),
                    'description' => '"simple", "configurable", or "kit".',
                    'resolve'     => static fn ($p) => $p->getType(),
                ],
                'featured' => [
                    'type'        => Type::boolean(),
                    'description' => 'Featured flag.',
                    'resolve'     => static fn ($p) => (bool) $p->getFeatured(),
                ],
                'newArrival' => [
                    'type'        => Type::boolean(),
                    'description' => 'New-arrival flag.',
                    'resolve'     => static fn ($p) => (bool) $p->isNewArrival(),
                ],
                'createdAt' => [
                    'type'        => Type::string(),
                    'description' => 'ISO-8601 creation timestamp.',
                    'resolve'     => static fn ($p) => $p->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ],
                'updatedAt' => [
                    'type'        => Type::string(),
                    'description' => 'ISO-8601 last-update timestamp.',
                    'resolve'     => static fn ($p) => $p->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                ],
            ],
        ]);
    }
}
