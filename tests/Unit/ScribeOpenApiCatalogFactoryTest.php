<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiCatalogFactory;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

pest()->group('Mcp');

$fixturePath = __DIR__ . '/../Fixtures/openapi.yaml';

$makeCatalog = static function (array $document): ApiDocumentationCatalogDto {
    try {
        return new ScribeOpenApiCatalogFactory(pathPrefixes: ['/api/', '/admin/'])->make(
            document: $document,
            artifact: 'fixture.yaml',
            fingerprint: 'fixture-fingerprint',
        );
    } catch (ApiDocumentationUnavailableException $exception) {
        throw new RuntimeException(
            'Не удалось построить OpenAPI catalog.',
            $exception->getCode(),
            previous: $exception,
        );
    }
};

$catalogError = static function (array $document): ApiDocumentationUnavailableException {
    try {
        new ScribeOpenApiCatalogFactory(pathPrefixes: ['/api/', '/admin/'])->make(
            document: $document,
            artifact: 'fixture.yaml',
            fingerprint: 'fixture-fingerprint',
        );
    } catch (ApiDocumentationUnavailableException $exception) {
        return $exception;
    }

    throw new RuntimeException('Ожидалась ошибка построения OpenAPI catalog.');
};

it('создаёт DTO и DTO collections из стандартных OpenAPI operations', function () use (
    $fixturePath,
    $makeCatalog,
): void {
    $document = Yaml::parseFile($fixturePath);
    $catalog = $makeCatalog($document);
    $endpoint = $catalog->endpoints->toCollection()->first();

    expect($endpoint)->toBeInstanceOf(ApiEndpointDto::class)
        ->and($catalog->endpoints)->toHaveCount(3)
        ->and($catalog->document)->toBeInstanceOf(Collection::class)
        ->and($endpoint?->tags)->toBeInstanceOf(Collection::class)
        ->and($endpoint?->responseStatuses)->toBeInstanceOf(Collection::class)
        ->and($endpoint?->operation)->toBeInstanceOf(Collection::class)
        ->and($endpoint?->pathItem)->toBeInstanceOf(Collection::class);
});

it('сохраняет operation дословно и отделяет path-level контекст', function () use (
    $fixturePath,
    $makeCatalog,
): void {
    $document = Yaml::parseFile($fixturePath);
    $catalog = $makeCatalog($document);
    $endpoint = $catalog->endpoints
        ->toCollection()
        ->firstWhere('id', 'GET /api/products/{id}');

    expect($endpoint?->operation->all())->toBe($document['paths']['/api/products/{id}']['get'])
        ->and($endpoint?->pathItem->all())->toBe([
            'parameters' => $document['paths']['/api/products/{id}']['parameters'],
        ])
        ->and($endpoint?->pathItem)->not->toHaveKey('get')
        ->and($catalog->document->get('components'))->toBe($document['components'])
        ->and($catalog->document)->not->toHaveKey('paths');
});

it('сохраняет стандартные response status keys и request body признак', function () use (
    $fixturePath,
    $makeCatalog,
): void {
    $document = Yaml::parseFile($fixturePath);
    $catalog = $makeCatalog($document);
    $endpoints = $catalog->endpoints
        ->toCollection()
        ->keyBy(static fn(ApiEndpointDto $endpoint): string => $endpoint->id);

    expect($endpoints->get('GET /api/products/{id}')?->responseStatuses->all())->toBe([200, 404])
        ->and($endpoints->get('GET /api/products/{id}')?->hasRequestBody)->toBeFalse()
        ->and($endpoints->get('OPTIONS /api/products/{id}')?->responseStatuses->all())->toBe([204])
        ->and($endpoints->get('POST /admin/products')?->responseStatuses->all())->toBe([201, 422])
        ->and($endpoints->get('POST /admin/products')?->hasRequestBody)->toBeTrue();
});

it('исключает extensions и невалидные ключи из производных response statuses', function () use (
    $makeCatalog,
): void {
    $catalog = $makeCatalog([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/api/items' => [
                    'get' => [
                        'responses' => [
                            99 => ['description' => 'Invalid'],
                            200 => ['description' => 'OK'],
                            600 => ['description' => 'Invalid'],
                            'default' => ['description' => 'Fallback'],
                            '2XX' => ['description' => 'Success'],
                            'x-internal' => ['description' => 'Extension'],
                            'invalid' => ['description' => 'Invalid'],
                        ],
                    ],
                ],
            ],
        ]);

    expect($catalog->endpoints->toCollection()->first()?->responseStatuses->all())
        ->toBe([200, 'default', '2XX']);
});

it('индексирует все стандартные OpenAPI HTTP methods', function () use ($makeCatalog): void {
    $operations = collect([
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
    ])->mapWithKeys(static fn(string $method): array => [
        $method => ['responses' => ['204' => ['description' => 'OK']]],
    ])->all();
    $catalog = $makeCatalog([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => ['/api/items' => $operations],
        ]);

    expect($catalog->endpoints->toCollection()->pluck('id')->all())->toBe([
        'DELETE /api/items',
        'GET /api/items',
        'HEAD /api/items',
        'OPTIONS /api/items',
        'PATCH /api/items',
        'POST /api/items',
        'PUT /api/items',
        'TRACE /api/items',
    ]);
});

it('разрешает локальный Path Item ref для discovery и сохраняет его в контексте', function () use (
    $fixturePath,
    $makeCatalog,
): void {
    $document = Yaml::parseFile($fixturePath);
    $document['paths']['/api/products/current'] = [
        '$ref' => '#/paths/~1api~1products~1{id}',
        'parameters' => [
            [
                'name' => 'locale',
                'in' => 'query',
                'schema' => ['type' => 'string'],
            ],
        ],
        'x-source' => 'local-reference',
    ];
    $catalog = $makeCatalog($document);
    $endpoint = $catalog->endpoints
        ->toCollection()
        ->firstWhere('id', 'GET /api/products/current');

    expect($endpoint)->toBeInstanceOf(ApiEndpointDto::class)
        ->and($endpoint?->operation->all())->toBe($document['paths']['/api/products/{id}']['get'])
        ->and($endpoint?->pathItem->get('$ref'))->toBe('#/paths/~1api~1products~1{id}')
        ->and($endpoint?->pathItem->get('parameters'))->toBe($document['paths']['/api/products/current']['parameters'])
        ->and($endpoint?->pathItem->get('x-source'))->toBe('local-reference');
});

it('отклоняет внешний Path Item ref контролируемой ошибкой', function () use ($catalogError): void {
    $exception = $catalogError([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/api/items' => ['$ref' => './paths.yaml#/items'],
            ],
        ]);

    expect($exception->getMessage())->toContain('Внешний Path Item');
});

it('разрешает цепочку Path Item refs и отклоняет отсутствующие или циклические refs', function () use (
    $catalogError,
    $makeCatalog,
): void {
    $document = [
        'openapi' => '3.0.3',
        'info' => ['title' => 'Test', 'version' => '1.0.0'],
        'paths' => [
            '/api/source' => [
                'get' => ['responses' => ['200' => ['description' => 'OK']]],
            ],
            '/api/alias' => ['$ref' => '#/paths/~1api~1source'],
            '/api/chained-alias' => ['$ref' => '#/paths/~1api~1alias'],
        ],
    ];
    $catalog = $makeCatalog($document);
    $missing = $catalogError(array_replace_recursive($document, [
        'paths' => [
            '/api/missing' => ['$ref' => '#/paths/~1api~1unknown'],
        ],
    ]));
    $cycle = $catalogError(array_replace_recursive($document, [
        'paths' => [
            '/api/cycle-a' => ['$ref' => '#/paths/~1api~1cycle-b'],
            '/api/cycle-b' => ['$ref' => '#/paths/~1api~1cycle-a'],
        ],
    ]));

    expect($catalog->endpoints->toCollection()->pluck('id'))->toContain('GET /api/chained-alias')
        ->and($missing->getMessage())->toContain('не найден')
        ->and($cycle->getMessage())->toContain('циклический');
});

it('индексирует произвольные пути без настроенного фильтра', function (): void {
    $catalog = new ScribeOpenApiCatalogFactory()->make(
        document: [
            'openapi' => '3.0.3',
            'paths' => [
                '/v1/items' => ['get' => ['responses' => ['200' => ['description' => 'OK']]]],
                '/health' => ['get' => ['responses' => ['200' => ['description' => 'OK']]]],
            ],
        ],
        artifact: 'fixture.yaml',
        fingerprint: 'fixture-fingerprint',
    );

    expect($catalog->endpoints->toCollection()->pluck('id')->all())
        ->toBe(['GET /health', 'GET /v1/items']);
});
