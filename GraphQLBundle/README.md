# Genaker GraphQL Bundle

A production-ready GraphQL server integration for OroCommerce, providing efficient, type-safe API access to business data.

## Overview

The Genaker GraphQL Bundle integrates the **webonyx/graphql-php** library with Orocommerce's security, caching, and ORM infrastructure. It exposes business entities (Products, Orders, etc.) through a modern GraphQL query interface alongside traditional REST endpoints.

### What is GraphQL?

GraphQL is a query language and runtime for APIs that allows clients to:
- **Request exactly what they need** — no over-fetching or under-fetching data
- **Fetch related data in a single query** — no N+1 round-trips to the server
- **Self-document via introspection** — built-in schema discovery for client tooling
- **Evolve APIs without versioning** — deprecation flags allow graceful migrations

## Who Should Use This Bundle?

### ✅ Ideal For:
- **Mobile & Single-Page Apps (SPAs)**  
  GraphQL reduces bandwidth and network overhead — critical for mobile users and offline-first apps.

- **Complex Data Requirements**  
  Fetch products with related images, pricing tier, inventory, and warehouse stock in one query.

- **Polyglot Clients**  
  iOS, Android, web, and backend integrations consume the same schema without custom resource layers.

- **Dashboard & Real-Time UIs**  
  Subscriptions (future) and field-level resolvers enable live data without polling.

- **Third-Party Integrations**  
  Marketplace connectors, billing systems, and analytics platforms query exactly the fields they need.

- **Microservices & Federated Architectures**  
  Apollo Federation support (planned) enables composing multiple GraphQL services.

### ❌ Not Ideal For:
- Simple, read-only public APIs (REST is sufficient)
- Bulk data exports (use GraphQL bulk queries responsibly; consider batch APIs instead)
- Server-to-server internal APIs where REST conventions suffice

## Key Advantages

### 1. **Single Round-Trip for Complex Queries**
**Before (REST):**
```
GET /api/products/123
GET /api/products/123/images
GET /api/products/123/pricing-tiers
GET /api/warehouses
```
**After (GraphQL):**
```graphql
query {
  product(id: 123) {
    name
    images { url }
    pricingTiers { currency amount }
    warehouse { name location }
  }
}
```

### 2. **Bandwidth Optimization**
- Only requested fields are serialized and transmitted
- Perfect for low-bandwidth scenarios (mobile networks)
- Reduced payload size improves perceived performance

### 3. **Strong, Self-Documenting Types**
- Schema is executable documentation
- IDEs (VS Code, WebStorm) provide autocompletion and inline help
- No manual API docs to keep in sync — they're generated from schema

### 4. **Easy Evolution Without Versioning**
```graphql
type Product {
  id: ID!
  name: String!
  price: Float!        # old field
  pricing: Pricing!    # new field (coexists peacefully)
  legacyPrice: Float @deprecated(reason: "Use pricing instead")
}
```

### 5. **Powerful Filtering & Pagination**
Built-in cursor-based pagination and complex filter expressions eliminate need for multiple endpoints.

### 6. **Security & Performance Built-In**
- OAuth2 Bearer token authentication (same as REST)
- Query complexity analysis prevents malicious queries
- Field-level resolver permissions
- Eager-loading strategies prevent N+1 queries

## Architecture

### Directory Structure
```
src/Genaker/Bundle/GraphQLBundle/
├── Controller/
│   ├── AbstractGraphQLController.php  # HTTP layer, request parsing
│   └── GraphQLController.php          # Concrete actions (executeAction, introspect)
├── Schema/
│   │   SchemaFactory.php              # Builds the TypeMap from resolver hints
│   ├── Type/
│   │   ├── QueryType.php              # Root query { product(...) { ... } }
│   │   ├── MutationType.php           # Root mutation { testMutation }
│   │   ├── ProductType.php            # Product entity mapped to GraphQL type
│   │   ├── ProductConnectionType.php  # Pagination cursor wrapper
│   │   └── ...
│   └── Resolver/
│       ├── ProductResolver.php        # Field resolvers for Product queries
│       └── MutationResolver.php       # Field resolvers for mutations
├── Tests/
│   ├── Integration/GraphQLIntegrationTest.php
│   └── phpunit.xml.dist
├── Resources/
│   ├── config/
│   │   └── services.yml
│   └── schema.graphql                 # SDL schema documentation
└── GenakerGraphQLBundle.php
```

### Schema Definition (SDL) – Extensible Modular Format

The GraphQL schema is documented in **GraphQL Schema Definition Language (SDL)** using a **modular, extensible format** at:
```
Resources/schema/          # Modular schema directory
├── shared-types.graphql   # Scalars, interfaces, common types
├── query.graphql          # Root Query type
├── mutation.graphql       # Root Mutation type
├── product.graphql        # Product resource (queries + types)
└── [order.graphql]        # Future: Order resource
```

**Main entry point:**
```
Resources/schema.graphql   # Composite schema (documents the structure)
```

#### Why Modular?

✅ **Add New Resources Without Modifying Core:**
```graphql
# New file: Resources/schema/order.graphql
type Order {
  id: ID!
  orderNumber: String!
  status: String!
}

extend type Query {
  orders(status: String): [Order!]!
}
```

✅ **Domain-Driven Organization:**
- Each resource in its own file
- Easy to find and maintain
- Clear dependencies between domains

✅ **Reusable Shared Types:**
- `shared-types.graphql` defines scalars, interfaces, errors
- All resources inherit base types

#### Viewing the Schema Files:
```bash
# View main schema documentation
cat src/Genaker/Bundle/GraphQLBundle/Resources/schema.graphql

# View product-specific definitions
cat src/Genaker/Bundle/GraphQLBundle/Resources/schema/product.graphql

# View shared types and interfaces
cat src/Genaker/Bundle/GraphQLBundle/Resources/schema/shared-types.graphql
```

#### IDE Support

GraphQL tooling supports modular schemas automatically:
- [Insomnia](https://insomnia.rest) — Reads all schema files  
- [GraphQL Playground](https://www.apollographql.com/docs/apollo-server/testing/graphql-playground/) — URL: `https://localhost:8000/admin/api/graphql`
- [VS Code GraphQL Extension](https://marketplace.visualstudio.com/items?itemName=GraphQL.vscode-graphql) — Auto-discovers schema/ directory

### Request Flow
```
POST /admin/api/graphql
  ↓
[OAuth2 api_secured Firewall]  ← Validates Bearer token
  ↓
GraphQLController::executeAction()
  ↓
AbstractGraphQLController::handleQuery()  ← Parses JSON body, extracts query
  ↓
SchemaFactory::createSchema()  ← Builds type map from resolvers
  ↓
GraphQL::executeQuery()  ← webonyx/graphql-php engine
  ↓
ProductResolver, etc.  ← Field-level business logic
  ↓
200 OK { data: { product: {...} }, errors?: [...] }
```

## Endpoints

All endpoints are protected by OAuth2 Bearer token authentication (firewall: `api_secured`).

### Introspection / Health Check
```
GET /admin/api/graphql
Authorization: Bearer <token>
Accept: application/json

Response:
{
  "status": "ok",
  "queries": {
    "product": "Product",
    "products": "[Product!]!"
  },
  "bundle": "GenakerGraphQLBundle",
  "version": "1.0.0"
}
```

### Execute Query
```
POST /admin/api/graphql
Authorization: Bearer <token>
Content-Type: application/json

{
  "query": "query { products(limit: 10 status: ENABLED) { edges { node { id name } } } }",
  "variables": {}
}

Response:
{
  "data": {
    "products": {
      "edges": [
        { "node": { "id": "1", "name": "Widget Pro" } },
        ...
      ]
    }
  }
}
```

### Execute Mutation
```
POST /admin/api/graphql
Authorization: Bearer <token>
Content-Type: application/json

{
  "query": "mutation { testMutation(message: \"Hello GraphQL\") }"
}

Response:
{
  "data": {
    "testMutation": true
  }
}
```

The `testMutation` is a reference implementation that always returns `true`. It's useful for:
- Testing mutation infrastructure
- Validating client mutation implementations
- Dry-running integration tests

## About OroCommerce

**OroCommerce** is an enterprise B2B e-commerce platform built on Symfony, designed for complex business requirements:

- **Multi-Channel Commerce** — unified catalog across B2B portals, marketplaces, and integrations
- **Business Rules Engine** — dynamic pricing, shipping, and order workflows
- **Rich Security Model** — user roles, ownership, organization hierarchies
- **Scalable Architecture** — real-time inventory sync, high-volume order processing
- **Flexible Data Model** — custom attributes, flexible product properties, extensible entities

This GraphQL bundle integrates seamlessly with OroCommerce's:
- **Doctrine ORM** — automatic lazy-loading and relationship resolution
- **Security Context** — user organization filtering, role-based field access
- **Cache System** — Result caching and query plan optimization
- **Event Dispatcher** — hooks for custom business logic

## Getting Started

### 1. Check Authentication
```bash
# Obtain an OAuth2 Bearer token
TOKEN=$(curl -X POST https://localhost:8000/oauth2-token \
  -d 'grant_type=client_credentials' \
  -d 'client_id=your_client' \
  -d 'client_secret=your_secret' \
  | jq -r '.access_token')

echo $TOKEN
```

### 2. Explore the Schema
```bash
curl -X GET https://localhost:8000/admin/api/graphql \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .
```

### 3. Run Your First Query
```bash
curl -X POST https://localhost:8000/admin/api/graphql \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "{ products(limit: 5) { edges { node { id name status } } } }"
  }' | jq .
```

### 4. Use GraphQL IDE (Optional)
Install [Insomnia](https://insomnia.rest) or [GraphQL Playground](https://www.apollographql.com/docs/apollo-server/testing/graphql-playground/) and point it at `https://localhost:8000/admin/api/graphql` with your Bearer token.

## Performance Best Practices

### 1. **Prefer Cursor Pagination Over Offset**
```graphql
# ❌ Avoid (O(N) scan)
query {
  products(offset: 10000 limit: 10) { ... }
}

# ✅ Use (O(1) lookup)
query {
  products(first: 10 after: "<cursor>") { ... }
}
```

### 2. **Select Only Required Fields**
```graphql
# ❌ Over-fetching
query {
  products { id name description images pricing data }
}

# ✅ Efficient
query {
  products { id name }
}
```

### 3. **Batch Related Queries**
```graphql
# ❌ Multiple queries
query { product(id: 1) { name } }
query { product(id: 2) { name } }

# ✅ Single query
query {
  p1: product(id: 1) { name }
  p2: product(id: 2) { name }
}
```

### 4. **Use Variables for Dynamic Values**
```graphql
# ❌ String interpolation (harder to cache)
query { product(id: $productId) { ... } }

# ✅ GraphQL variables (query plan cached)
query GetProduct($id: ID!) {
  product(id: $id) { ... }
}
```

## Testing

Run the integration test suite:
```bash
bin/phpunit -c src/Genaker/Bundle/GraphQLBundle/Tests/phpunit.xml.dist --testdox
```

Tests cover:
- ✅ Schema introspection
- ✅ Query execution with authentication
- ✅ Malformed request handling (400 errors)
- ✅ Product filter and pagination
- ✅ Content-Type negotiation

## Contributing

### Adding a New Resource (Extensible Schema Pattern)

The modular schema structure makes it easy to add new resources without modifying core files.

#### 1. Create a New SDL File

Create `Resources/schema/{resource}.graphql`:

```graphql
"""
Order GraphQL types and queries.
"""

type Order implements Timestamped {
  id: ID!
  orderNumber: String!
  status: String!
  totalAmount: Float!
  createdAt: DateTime!
  updatedAt: DateTime!
}

extend type Query {
  """Get a single order by ID"""
  order(id: Int!): Order
  
  """List orders with pagination"""
  orders(status: String, limit: Int, offset: Int): [Order!]!
}
```

#### 2. Create PHP Type Class

Create `Schema/Type/OrderType.php`:

```php
<?php
namespace Genaker\Bundle\GraphQLBundle\Schema\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class OrderType extends ObjectType {
    public function __construct(OrderResolver $resolver) {
        parent::__construct([
            'name' => 'Order',
            'fields' => [
                'id'          => ['type' => Type::nonNull(Type::id())],
                'orderNumber' => ['type' => Type::nonNull(Type::string())],
                'status'      => ['type' => Type::string()],
                'totalAmount' => ['type' => Type::float()],
            ],
            'interfaces' => [/* Timestamped interface */],
        ]);
    }
}
```

#### 3. Create Resolver

Create `Schema/Resolver/OrderResolver.php`:

```php
<?php
namespace Genaker\Bundle\GraphQLBundle\Schema\Resolver;

class OrderResolver {
    public function resolveOrder(mixed $obj, array $args): ?Order { /* ... */ }
    public function resolveOrders(mixed $obj, array $args): array { /* ... */ }
}
```

#### 4. Register in SchemaFactory

Update `Schema/SchemaFactory.php`:

```php
public function __construct(
    private readonly OrderType $orderType,  // Add
    // ... other types
) {}

public function createSchema(): Schema {
    return new Schema(
        SchemaConfig::create()
            ->setQuery($this->queryType)
            ->setMutation($this->mutationType)
            ->setTypes([
                $this->productType,
                $this->orderType,  // Add
            ])
    );
}
```

#### 5. Register Services

Update `Resources/config/services.yml`:

```yaml
Genaker\Bundle\GraphQLBundle\Schema\Type\OrderType:
  shared: true

Genaker\Bundle\GraphQLBundle\Schema\Resolver\OrderResolver: {}

Genaker\Bundle\GraphQLBundle\Schema\SchemaFactory:
  arguments:
    $orderType: '@Genaker\Bundle\GraphQLBundle\Schema\Type\OrderType'
```

#### 6. Add Tests

Add tests in `Tests/Integration/GraphQLIntegrationTest.php`:

```php
public function testExecute_order_byId_returnsAllFields(): void {
    $response = $this->post(
        self::ENDPOINT,
        ['query' => '{ order(id: 1) { id orderNumber status totalAmount } }'],
        ['Authorization' => 'Bearer ' . $this->token]
    );
    
    $this->assertSame(200, $response->getStatusCode());
    $this->assertArrayHasKey('data', json_decode($response->getContent(), true));
}
```

### Schema Composition

The schema composition happens automatically via:
- `SchemaFactory::createSchema()` — Registers all types
- Type fields — Defined via resolvers
- `setTypes()` — Makes types discoverable for introspection

### Extending Root Query/Mutation

For more advanced cases, use GraphQL's `extend` directive directly in SDL files:

```graphql
# Resources/schema/order.graphql
extend type Query {
  orders(status: String): [Order!]!
}

extend type Mutation {
  createOrder(input: CreateOrderInput!): Order!
}
```

Then register in PHP without modifying QueryType/MutationType.

### Adding Subscriptions (Future)

The schema currently supports queries and mutations only. WebSocket subscriptions are planned for real-time notifications:

```graphql
type Subscription {
  orderStatusChanged(orderId: Int!): Order!
}
```

## Troubleshooting

### 401/403 Authentication Error
```
Check your Bearer token is valid:
  bin/console debug:security:token --token="<your_token>"
```

### Query Returns Null
```
Ensure the object exists in the database:
  SELECT * FROM oro_product WHERE id = <id>;
```

### N+1 Query Problem
```
Enable Doctrine query logging:
  // In GraphQL resolver, use eager loading:
  ->joinEagerLoad('images')
  ->joinEagerLoad('pricingTiers')
```

## Resources

- **GraphQL Official Docs:** https://graphql.org
- **Webonyx GraphQL-PHP:** https://github.com/webonyx/graphql-php
- **Apollo Client (JavaScript):** https://www.apollographql.com/docs/react
- **OroCommerce Docs:** https://doc.oroinc.com

## License

Property of Genaker / Licensed under the same terms as OroCommerce Enterprise Edition.

---

**Questions?** Contact the ERP integration team or check the [Oro Developer Guide](https://doc.oroinc.com/backend/architecture/).
