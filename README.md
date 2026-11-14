# Scribe MCP

Read-only MCP tools for Scribe-generated OpenAPI 3 YAML in Laravel 13 / PHP 8.4.
The server reads a local artifact; it never calls the documented API or generates documentation during a request.

## Installation

Until the package is available on Packagist, add the public VCS repository:

```sh
composer config repositories.scribe-mcp vcs https://github.com/AleksandrSpicyn/scribe-mcp
composer require aleksandrspicyn/scribe-mcp:^1.0
php artisan scribe:generate
```

Scribe is installed and configured by the host application. It is not a runtime dependency of this package: an existing OpenAPI artifact is enough.
The service provider is discovered automatically. It binds a scoped file source and merges configuration; it does **not** expose an HTTP route automatically.

Register the transport in `routes/ai.php`, choosing the application's authentication and rate limiting middleware:

```php
use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/api-documentation', ApiDocumentationServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
```

For local trusted clients, register `Mcp::local('scribe', ApiDocumentationServer::class)` and run `php artisan mcp:start scribe`.
Do not expose private API documentation without authentication and appropriate authorization.

## Configuration

```sh
php artisan vendor:publish --tag=scribe-mcp-config
```

`config/scribe-mcp.php`:

```php
return [
    'openapi_path' => env('SCRIBE_MCP_OPENAPI_PATH', storage_path('app/scribe/openapi.yaml')),
    'search_limit' => 50,
    'path_prefixes' => [],
];
```

An empty prefix list indexes every documented path. Use `['/api/', '/admin/']` to restrict the catalog.
`search_limit` caps returned results; tool input accepts `limit` from 1 to 50 (default 20).
Use an absolute local artifact path. Contents are fingerprinted on every read, so replacing an artifact updates the catalog even if its byte size stays unchanged.

## Tools

1. `search-api-endpoints`: optional `query`, `method`, `group`, `authenticated`, `limit`; returns canonical IDs and compact endpoint summaries.
2. `get-api-endpoint`: required `endpoint_id` such as `GET /api/products/{id}`; returns the operation, path-level context, and document-level context, including components and security schemes.

Both tools are marked read-only and idempotent. Results retain OpenAPI objects, arrays, nulls, response examples, schemas and extensions. Only local Path Item `$ref` references are resolved; external and cyclic Path Item references return a controlled error. Operation/schema references remain in their original form with document context. Missing, malformed or invalid artifacts return MCP errors. No resources or prompts are registered.
Tool descriptions and controlled errors are currently in Russian.

## Development

```sh
composer install
composer test
composer analyse
composer format
```

Tests run with Orchestra Testbench and Pest, without a database or a host application. CI checks PHP 8.4 and 8.5.

To edit the package while using it in a Laravel project, temporarily override the VCS repository with a local path repository:

```sh
composer config repositories.scribe-mcp '{"type":"path","url":"../scribe-mcp","options":{"symlink":true,"versions":{"aleksandrspicyn/scribe-mcp":"1.0.0"}}}'
composer update aleksandrspicyn/scribe-mcp --minimal-changes
```

Edits in `../scribe-mcp/src` are now used directly by the application. After testing, restore the VCS repository and update the package again before committing the host application's lock file:

```sh
composer config repositories.scribe-mcp vcs https://github.com/AleksandrSpicyn/scribe-mcp
composer update aleksandrspicyn/scribe-mcp --minimal-changes --prefer-dist
```

See [Laravel package discovery](https://laravel.com/docs/13.x/packages#package-discovery) and [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path).

## License

MIT; see [LICENSE](LICENSE).
