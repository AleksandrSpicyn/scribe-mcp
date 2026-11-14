<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\ScribeOpenApiParser;

pest()->group('Mcp');

$parseOpenApi = static function (string $contents): array {
    try {
        return new ScribeOpenApiParser()->parse($contents);
    } catch (ApiDocumentationUnavailableException $exception) {
        throw new RuntimeException(
            'Не удалось разобрать валидный OpenAPI.',
            $exception->getCode(),
            previous: $exception,
        );
    }
};

$parseError = static function (string $contents): ApiDocumentationUnavailableException {
    try {
        new ScribeOpenApiParser()->parse($contents);
    } catch (ApiDocumentationUnavailableException $exception) {
        return $exception;
    }

    throw new RuntimeException('Ожидалась ошибка разбора OpenAPI.');
};

it('разбирает стандартный OpenAPI документ без изменения данных', function () use ($parseOpenApi): void {
    $contents = <<<'YAML'
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /api/items:
    get:
      responses:
        200:
          description: OK
YAML;

    $document = $parseOpenApi($contents);

    expect($document['openapi'])->toBe('3.0.3')
        ->and($document['info'])->toBeInstanceOf(stdClass::class)
        ->and($document['paths']['/api/items'])->toBeInstanceOf(stdClass::class)
        ->and($document['paths']['/api/items']->get)->toBeInstanceOf(stdClass::class)
        ->and($document['paths']['/api/items']->get->responses->{'200'}->description)->toBe('OK');
});

it('сохраняет различие между пустыми OpenAPI object и array', function () use ($parseOpenApi): void {
    $document = $parseOpenApi(<<<'YAML'
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /api/items:
    get:
      security:
        - {}
      responses:
        200:
          description: OK
          content:
            application/json:
              example:
                filters: {}
                items: []
YAML);

    $operation = $document['paths']['/api/items']->get;
    $example = $operation->responses->{'200'}->content->{'application/json'}->example;

    expect($operation->security[0])->toBeInstanceOf(stdClass::class)
        ->and($example->filters)->toBeInstanceOf(stdClass::class)
        ->and($example->items)->toBe([]);
});

it('принимает пустой Paths Object', function () use ($parseOpenApi): void {
    $document = $parseOpenApi(
        "openapi: 3.0.3\ninfo: { title: Test, version: 1.0.0 }\npaths: {}\n",
    );

    expect($document['paths'])->toBe([]);
});

it('отклоняет синтаксически повреждённый YAML', function () use ($parseError): void {
    expect($parseError("openapi: 3.0.3\npaths: [\n")->getMessage())
        ->toContain('не удалось разобрать');
});

it('отклоняет документ без обязательной OpenAPI 3 info или object paths', function () use ($parseError): void {
    expect($parseError("info: { title: Test, version: 1.0.0 }\npaths: {}\n"))
        ->toBeInstanceOf(ApiDocumentationUnavailableException::class)
        ->and($parseError("openapi: 2.0\ninfo: { title: Test, version: 1.0.0 }\npaths: {}\n"))
        ->toBeInstanceOf(ApiDocumentationUnavailableException::class)
        ->and($parseError("openapi: 3.0.3\npaths: {}\n"))
        ->toBeInstanceOf(ApiDocumentationUnavailableException::class)
        ->and($parseError("openapi: 3.0.3\ninfo: { title: Test, version: 1.0.0 }\npaths: []\n"))
        ->toBeInstanceOf(ApiDocumentationUnavailableException::class);
});
