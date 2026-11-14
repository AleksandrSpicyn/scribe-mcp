<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\Contracts\ScribeOpenApiSource;
use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use AleksandrSpicyn\ScribeMcp\Tools\SearchApiEndpointsTool;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    config()->set('scribe-mcp.openapi_path', __DIR__ . '/../Fixtures/openapi.yaml');
    Http::preventStrayRequests();
});

it('читает артефакт через автоматически зарегистрированный source без HTTP запросов', function (): void {
    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, ['query' => 'товар'])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->where('endpoints.0.id', 'GET /api/products/{id}')->has('source')->etc();
        });

    Http::assertNothingSent();
});

it('не публикует HTTP маршрут без явного выбора приложения', function (): void {
    expect(collect(app('router')->getRoutes()->getRoutes())->filter(
        static fn(Route $route): bool => str_starts_with($route->uri(), 'mcp/'),
    ))->toBeEmpty();
});

it('создаёт новый source после завершения scope', function (): void {
    $source = app(ScribeOpenApiSource::class);

    expect(app(ScribeOpenApiSource::class))->toBe($source);

    app()->forgetScopedInstances();

    expect(app(ScribeOpenApiSource::class))->not->toBe($source);
});

it('применяет настроенные префиксы путей', function (): void {
    config()->set('scribe-mcp.path_prefixes', ['/admin/']);

    ApiDocumentationServer::tool(SearchApiEndpointsTool::class)
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('endpoints', 1)->where('endpoints.0.id', 'POST /admin/products')->etc();
        });
});

it('ограничивает выдачу настройкой пакета', function (): void {
    config()->set('scribe-mcp.search_limit', 1);

    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, ['limit' => 50])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('endpoints', 1)->where('endpoints.0.id', 'GET /api/products/{id}')->etc();
        });
});

it('возвращает MCP ошибку при отсутствующем артефакте', function (): void {
    config()->set('scribe-mcp.openapi_path', __DIR__ . '/../Fixtures/missing.yaml');

    ApiDocumentationServer::tool(SearchApiEndpointsTool::class)->assertHasErrors(['не найден']);
});
