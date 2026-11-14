<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Attributes\McpInput;
use AleksandrSpicyn\ScribeMcp\Dto\Input\GetApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use AleksandrSpicyn\ScribeMcp\Tools\GetApiEndpointTool;
use AleksandrSpicyn\ScribeMcp\Tools\SearchApiEndpointsTool;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Symfony\Component\Yaml\Yaml;

pest()->group('Mcp');

$fixturePath = __DIR__ . '/../Fixtures/openapi.yaml';

beforeEach(function () use ($fixturePath): void {
    config()->set('scribe-mcp.openapi_path', $fixturePath);
});

it('ищет компактные endpoint из публичного и административного OpenAPI', function (): void {
    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, [
        'query' => 'товар',
        'limit' => 10,
    ])->assertOk()
        ->assertName('search-api-endpoints')
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('endpoints', 2)
                ->where('endpoints.0.id', 'GET /api/products/{id}')
                ->where('endpoints.0.method', 'GET')
                ->where('endpoints.0.path', '/api/products/{id}')
                ->where('endpoints.0.summary', 'Получить товар')
                ->where('endpoints.1.id', 'POST /admin/products')
                ->where('endpoints.1.authenticated', true)
                ->has('source')
                ->etc();
        });
});

it('фильтрует endpoint по методу и аутентификации', function (): void {
    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, [
        'method' => 'POST',
        'authenticated' => true,
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json): void {
            $json->has('endpoints', 1)
                ->where('endpoints.0.id', 'POST /admin/products')
                ->where('endpoints.0.tags.0', 'Администрирование товаров')
                ->etc();
        });
});

it('возвращает endpoint как lossless OpenAPI fragment', function (): void {
    $openApi = Yaml::parseFile(__DIR__ . '/../Fixtures/openapi.yaml');

    ApiDocumentationServer::tool(GetApiEndpointTool::class, [
        'endpoint_id' => 'GET /api/products/{id}',
    ])->assertOk()
        ->assertName('get-api-endpoint')
        ->assertStructuredContent(function (AssertableJson $json) use ($openApi): void {
            $json->where('endpoint.id', 'GET /api/products/{id}')
                ->where('endpoint.method', 'GET')
                ->where('endpoint.path', '/api/products/{id}')
                ->where('endpoint.operation', $openApi['paths']['/api/products/{id}']['get'])
                ->where('endpoint.path_item.parameters', $openApi['paths']['/api/products/{id}']['parameters'])
                ->where('document.security', $openApi['security'])
                ->where('document.components', $openApi['components'])
                ->where('endpoint.operation.responses.200.content.application/json.examples.пустой_результат.value.data', [])
                ->where('endpoint.operation.responses.200.content.application/json.examples.пустой_результат.value.meta.source', 0)
                ->where('endpoint.operation.responses.200.content.application/json.examples.товар.value.data.0.available', false)
                ->where('endpoint.operation.responses.200.content.application/json.examples.товар.value.meta.filters', null)
                ->has('source')
                ->etc();
        });
});

it('возвращает authenticated endpoint с request body без потери operation', function (): void {
    $openApi = Yaml::parseFile(__DIR__ . '/../Fixtures/openapi.yaml');

    ApiDocumentationServer::tool(GetApiEndpointTool::class, [
        'endpoint_id' => 'POST /admin/products',
    ])->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($openApi): void {
            $json->where('endpoint.operation', $openApi['paths']['/admin/products']['post'])
                ->where('endpoint.operation.security', [['integrationToken' => []]])
                ->where('endpoint.operation.requestBody.content.application/json.schema.properties.state.default', 'draft')
                ->where('document.components.securitySchemes.integrationToken.scheme', 'bearer')
                ->etc();
        });
});

it('возвращает контролируемую ошибку для неизвестного endpoint', function (): void {
    ApiDocumentationServer::tool(GetApiEndpointTool::class, [
        'endpoint_id' => 'GET /api/missing',
    ])->assertHasErrors(['Endpoint не найден']);
});

it('валидирует обязательный canonical endpoint id', function (): void {
    ApiDocumentationServer::tool(GetApiEndpointTool::class)
        ->assertHasErrors();
});

it('валидирует аргументы поиска на MCP DTO boundary', function (): void {
    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, [
        'query' => str_repeat('a', 501),
        'method' => 'CONNECT',
        'limit' => 51,
    ])->assertHasErrors();
});

it('получает валидированные DTO напрямую в transport методах', function (): void {
    $toolInputs = [
        SearchApiEndpointsTool::class => SearchApiEndpointsDto::class,
        GetApiEndpointTool::class => GetApiEndpointDto::class,
    ];

    foreach ($toolInputs as $tool => $dto) {
        try {
            $parameter = new ReflectionMethod($tool, 'handle')->getParameters()[0];
        } catch (ReflectionException $exception) {
            throw new RuntimeException(
                'Не удалось проверить MCP DTO boundary.',
                $exception->getCode(),
                previous: $exception,
            );
        }

        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            throw new RuntimeException('MCP input должен иметь именованный DTO-тип.');
        }

        expect($type->getName())->toBe($dto)
            ->and($parameter->getAttributes(McpInput::class))->toHaveCount(1);
    }
});

it('регистрирует ровно два read-only idempotent tools без resources и prompts', function (): void {
    $context = new ApiDocumentationServer(new FakeTransporter())->createContext();
    $tools = $context->tools();

    expect($tools->map->name()->all())->toBe([
        'search-api-endpoints',
        'get-api-endpoint',
    ])->and($tools->map->annotations()->all())->each->toBe([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ])->and($context->resources())->toBeEmpty()
        ->and($context->prompts())->toBeEmpty()
        ->and($context->instructions)->toContain('search-api-endpoints -> get-api-endpoint')
        ->and($context->instructions)->not->toContain('frontend');
});
