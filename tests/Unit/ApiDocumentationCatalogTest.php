<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Actions\GetApiEndpointAction;
use AleksandrSpicyn\ScribeMcp\Actions\SearchApiEndpointsAction;
use AleksandrSpicyn\ScribeMcp\Dto\Input\GetApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\GetApiEndpointResultDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\SearchApiEndpointsResultDto;
use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationException;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiEndpointNotFoundException;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiCatalogFactory;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiFileSource;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiParser;
use AleksandrSpicyn\ScribeMcp\Support\ApiEndpointSearchMatcher;
use AleksandrSpicyn\ScribeMcp\Tasks\FindApiEndpointTask;
use AleksandrSpicyn\ScribeMcp\Tasks\GetApiDocumentationCatalogTask;
use AleksandrSpicyn\ScribeMcp\Tasks\SearchApiEndpointsTask;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

pest()->group('Mcp');

$fixturePath = (string)realpath(__DIR__ . '/../Fixtures/openapi.yaml');

$temporaryOpenApi = static function () use ($fixturePath): string {
    $path = tempnam(sys_get_temp_dir(), 'scribe-mcp-openapi-');

    if ($path === false || copy($fixturePath, $path) === false) {
        throw new RuntimeException('Не удалось подготовить временный OpenAPI fixture.');
    }

    return $path;
};

$sourceFor = static fn(string $path): ScribeOpenApiFileSource => new ScribeOpenApiFileSource(
    files: new Filesystem(),
    parser: new ScribeOpenApiParser(),
    catalogFactory: new ScribeOpenApiCatalogFactory(pathPrefixes: ['/api/', '/admin/']),
    openApiPath: $path,
);

$readCatalog = static function (ScribeOpenApiFileSource $source): ApiDocumentationCatalogDto {
    try {
        return $source->read();
    } catch (ApiDocumentationUnavailableException $exception) {
        throw new RuntimeException(
            'Не удалось прочитать OpenAPI catalog.',
            $exception->getCode(),
            previous: $exception,
        );
    }
};

$readError = static function (ScribeOpenApiFileSource $source): ApiDocumentationUnavailableException {
    try {
        $source->read();
    } catch (ApiDocumentationUnavailableException $exception) {
        return $exception;
    }

    throw new RuntimeException('Ожидалась ошибка чтения OpenAPI catalog.');
};

$searchEndpoints = static function (
    SearchApiEndpointsAction $action,
    SearchApiEndpointsDto $input,
): SearchApiEndpointsResultDto {
    try {
        return $action->run($input);
    } catch (ApiDocumentationUnavailableException $exception) {
        throw new RuntimeException(
            'Не удалось выполнить поиск OpenAPI endpoint.',
            $exception->getCode(),
            previous: $exception,
        );
    }
};

$getEndpoint = static function (
    GetApiEndpointAction $action,
    GetApiEndpointDto $input,
): GetApiEndpointResultDto {
    try {
        return $action->run($input);
    } catch (ApiDocumentationException $exception) {
        throw new RuntimeException(
            'Не удалось получить OpenAPI endpoint.',
            $exception->getCode(),
            previous: $exception,
        );
    }
};

$getEndpointError = static function (
    GetApiEndpointAction $action,
    GetApiEndpointDto $input,
): ApiDocumentationException {
    try {
        $action->run($input);
    } catch (ApiDocumentationException $exception) {
        return $exception;
    }

    throw new RuntimeException('Ожидалась ошибка получения OpenAPI endpoint.');
};

$writeDocument = static function (string $path, array $document): void {
    file_put_contents($path, Yaml::dump($document, 20, 2));
};

it('строит канонические api и admin endpoint, сохраняя OpenAPI контекст и source metadata', function () use (
    $fixturePath,
    $readCatalog,
    $sourceFor,
): void {
    $catalog = $readCatalog($sourceFor($fixturePath));
    $endpoints = $catalog->endpoints
        ->toCollection()
        ->keyBy(static fn(ApiEndpointDto $endpoint): string => $endpoint->id);
    $api = $endpoints->get('GET /api/products/{id}');
    $admin = $endpoints->get('POST /admin/products');

    expect($catalog->source->artifact)->toBe($fixturePath)
        ->and($catalog->source->openApiVersion)->toBe('3.0.3')
        ->and($catalog->source->endpointCount)->toBe(3)
        ->and($catalog->source->fingerprint)->toHaveLength(64)
        ->and($catalog->document)->toBeInstanceOf(Collection::class)
        ->and($catalog->document->get('components'))->toBeInstanceOf(stdClass::class)
        ->and($catalog->document->has('paths'))->toBeFalse()
        ->and($api)->toBeInstanceOf(ApiEndpointDto::class)
        ->and($api?->operation)->toBeInstanceOf(Collection::class)
        ->and($api?->operation->get('responses'))->toBeInstanceOf(stdClass::class)
        ->and($api?->pathItem)->toBeInstanceOf(Collection::class)
        ->and($api?->pathItem->get('parameters'))->toBeArray()
        ->and($admin)->toBeInstanceOf(ApiEndpointDto::class)
        ->and($admin?->operation->get('requestBody'))->toBeInstanceOf(stdClass::class);
});

it('фильтрует Unicode поиск, группу, method, auth и лимит в каноническом порядке', function () use (
    $searchEndpoints,
    $sourceFor,
    $temporaryOpenApi,
    $writeDocument,
): void {
    $path = $temporaryOpenApi();
    $document = Yaml::parseFile($path);
    $document['paths']['/api/public-products'] = [
        'get' => [
            'summary' => 'Публичные товары',
            'tags' => ['Товары'],
            'security' => [],
            'responses' => ['200' => ['description' => 'OK']],
        ],
    ];
    $document['paths']['/api/anonymous-products'] = [
        'get' => [
            'summary' => 'Анонимные товары',
            'tags' => ['Товары'],
            'security' => [[]],
            'responses' => ['200' => ['description' => 'OK']],
        ],
    ];
    $writeDocument($path, $document);

    try {
        $catalogTask = new GetApiDocumentationCatalogTask($sourceFor($path));
        $action = new SearchApiEndpointsAction(
            $catalogTask,
            new SearchApiEndpointsTask(new ApiEndpointSearchMatcher(), 2),
        );

        $unicode = $searchEndpoints($action, new SearchApiEndpointsDto(query: 'КАРТОЧКУ'));
        $group = $searchEndpoints($action, new SearchApiEndpointsDto(group: 'АДМИНИСТРИРОВАНИЕ ТОВАРОВ'));
        $method = $searchEndpoints($action, new SearchApiEndpointsDto(method: HttpMethod::POST));
        $public = $searchEndpoints($action, new SearchApiEndpointsDto(authenticated: false));
        $limited = $searchEndpoints($action, new SearchApiEndpointsDto(limit: 50));

        expect($unicode->endpoints->toCollection()->pluck('id')->all())->toBe(['GET /api/products/{id}'])
            ->and($group->endpoints->toCollection()->pluck('id')->all())->toBe(['POST /admin/products'])
            ->and($method->endpoints->toCollection()->pluck('id')->all())->toBe(['POST /admin/products'])
            ->and($public->endpoints->toCollection()->pluck('id')->all())->toBe([
                'GET /api/anonymous-products',
                'GET /api/public-products',
            ])
            ->and($limited->endpoints->toCollection()->pluck('id')->all())->toBe([
                'GET /api/anonymous-products',
                'GET /api/products/{id}',
            ]);
    } finally {
        unlink($path);
    }
});

it('определяет effective auth из root и operation security', function () use (
    $readCatalog,
    $sourceFor,
    $temporaryOpenApi,
    $writeDocument,
): void {
    $path = $temporaryOpenApi();
    $document = Yaml::parseFile($path);
    $document['paths']['/api/public-products'] = [
        'get' => [
            'security' => [],
            'responses' => ['200' => ['description' => 'OK']],
        ],
    ];
    $document['paths']['/api/anonymous-products'] = [
        'get' => [
            'security' => [[]],
            'responses' => ['200' => ['description' => 'OK']],
        ],
    ];
    $document['paths']['/api/optional-auth-products'] = [
        'get' => [
            'security' => [[], ['bearerAuth' => []]],
            'responses' => ['200' => ['description' => 'OK']],
        ],
    ];
    $writeDocument($path, $document);

    try {
        $endpoints = $readCatalog($sourceFor($path))
            ->endpoints
            ->toCollection()
            ->keyBy(static fn(ApiEndpointDto $endpoint): string => $endpoint->id);

        expect($endpoints->get('GET /api/products/{id}')?->authenticated)->toBeTrue()
            ->and($endpoints->get('POST /admin/products')?->authenticated)->toBeTrue()
            ->and($endpoints->get('GET /api/public-products')?->authenticated)->toBeFalse()
            ->and($endpoints->get('GET /api/anonymous-products')?->authenticated)->toBeFalse()
            ->and($endpoints->get('GET /api/optional-auth-products')?->authenticated)->toBeFalse();
    } finally {
        unlink($path);
    }
});

it('возвращает контролируемую ошибку при отсутствии OpenAPI файла', function () use (
    $readError,
    $sourceFor,
): void {
    expect($readError($sourceFor('/tmp/scribe-mcp-openapi-does-not-exist.yaml'))->getMessage())
        ->toContain('не найден');
});

it('возвращает контролируемую ошибку при повреждённом YAML', function () use ($readError, $sourceFor): void {
    $path = tempnam(sys_get_temp_dir(), 'scribe-mcp-openapi-invalid-');

    if ($path === false) {
        throw new RuntimeException('Не удалось создать временный файл.');
    }

    file_put_contents($path, "openapi: 3.0.3\npaths: [\n");

    try {
        expect($readError($sourceFor($path))->getMessage())->toContain('не удалось разобрать');
    } finally {
        unlink($path);
    }
});

it('возвращает контролируемую ошибку при отсутствии обязательных openapi или paths', function () use (
    $readError,
    $sourceFor,
): void {
    $path = tempnam(sys_get_temp_dir(), 'scribe-mcp-openapi-incomplete-');

    if ($path === false) {
        throw new RuntimeException('Не удалось создать временный файл.');
    }

    try {
        file_put_contents($path, "paths: {}\n");

        expect($readError($sourceFor($path))->getMessage())
            ->toContain('корректные openapi, info или object paths');

        file_put_contents($path, "openapi: 3.0.3\n");

        expect($readError($sourceFor($path))->getMessage())
            ->toContain('корректные openapi, info или object paths');
    } finally {
        unlink($path);
    }
});

it('кэширует неизменённый source и обновляет fingerprint при изменении одинакового размера', function () use (
    $readCatalog,
    $sourceFor,
    $temporaryOpenApi,
): void {
    $path = $temporaryOpenApi();

    try {
        $source = $sourceFor($path);
        $firstCatalog = $readCatalog($source);
        $unchangedCatalog = $readCatalog($source);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать временный OpenAPI fixture.');
        }

        $changedContents = str_replace('Получить товар', 'Получить заказ', $contents, $replacements);

        expect($replacements)->toBe(1)
            ->and(strlen($changedContents))->toBe(strlen($contents));

        file_put_contents($path, $changedContents);

        $changedCatalog = $readCatalog($source);
        $endpoint = new FindApiEndpointTask()->run($changedCatalog, 'GET /api/products/{id}');

        expect($unchangedCatalog)->toBe($firstCatalog)
            ->and($changedCatalog)->not->toBe($firstCatalog)
            ->and($changedCatalog->source->fingerprint)->not->toBe($firstCatalog->source->fingerprint)
            ->and($endpoint?->summary)->toBe('Получить заказ');
    } finally {
        unlink($path);
    }
});

it('actions и tasks используют только канонический endpoint id', function () use (
    $fixturePath,
    $getEndpoint,
    $getEndpointError,
    $readCatalog,
    $searchEndpoints,
    $sourceFor,
): void {
    $catalogTask = new GetApiDocumentationCatalogTask($sourceFor($fixturePath));
    $findTask = new FindApiEndpointTask();
    $search = new SearchApiEndpointsAction(
        $catalogTask,
        new SearchApiEndpointsTask(new ApiEndpointSearchMatcher(), 50),
    );
    $get = new GetApiEndpointAction($catalogTask, $findTask);

    $searchResult = $searchEndpoints($search, new SearchApiEndpointsDto(query: 'товар', method: HttpMethod::GET));
    $getResult = $getEndpoint($get, new GetApiEndpointDto('GET /api/products/{id}'));
    $catalog = $readCatalog($sourceFor($fixturePath));
    $missingEndpoint = $getEndpointError($get, new GetApiEndpointDto('products.show'));

    expect($searchResult->endpoints->toCollection()->pluck('id')->all())->toBe(['GET /api/products/{id}'])
        ->and($getResult->endpoint->id)->toBe('GET /api/products/{id}')
        ->and($findTask->run($catalog, 'products.show'))->toBeNull()
        ->and($missingEndpoint)->toBeInstanceOf(ApiEndpointNotFoundException::class);
});

it('исключает пути вне api и admin и индексирует OPTIONS method', function () use (
    $readCatalog,
    $sourceFor,
    $temporaryOpenApi,
    $writeDocument,
): void {
    $path = $temporaryOpenApi();
    $document = Yaml::parseFile($path);
    $document['paths']['/health'] = [
        'get' => ['responses' => ['200' => ['description' => 'OK']]],
    ];
    $document['paths']['/webhooks/events'] = [
        'post' => ['responses' => ['202' => ['description' => 'Accepted']]],
    ];
    $document['paths']['/api/products/options'] = [
        'options' => ['responses' => ['204' => ['description' => 'No content']]],
    ];
    $writeDocument($path, $document);

    try {
        $identifiers = $readCatalog($sourceFor($path))
            ->endpoints
            ->toCollection()
            ->pluck('id');

        expect($identifiers)->toHaveCount(4)
            ->and($identifiers)->not->toContain('GET /health')
            ->and($identifiers)->not->toContain('POST /webhooks/events')
            ->and($identifiers)->toContain('OPTIONS /api/products/{id}')
            ->and($identifiers)->toContain('OPTIONS /api/products/options');
    } finally {
        unlink($path);
    }
});
