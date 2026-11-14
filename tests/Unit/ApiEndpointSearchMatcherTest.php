<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use AleksandrSpicyn\ScribeMcp\Support\ApiEndpointSearchMatcher;
use Illuminate\Support\Collection;

pest()->group('Mcp');

$endpoint = static function (): ApiEndpointDto {
    /** @var Collection<int, int|string> $responseStatuses */
    $responseStatuses = collect([200]);

    return new ApiEndpointDto(
        id: 'GET /api/products',
        method: HttpMethod::GET,
        path: '/api/products',
        summary: 'Список товаров',
        description: 'Возвращает каталог.',
        tags: collect(['Каталог', 'Товары']),
        authenticated: false,
        hasRequestBody: false,
        responseStatuses: $responseStatuses,
        operation: collect(['responses' => [200 => ['description' => 'OK']]]),
        pathItem: collect(),
    );
};

it('ищет без учёта регистра по canonical id и OpenAPI metadata', function () use ($endpoint): void {
    $matcher = new ApiEndpointSearchMatcher();

    expect($matcher->matches($endpoint(), new SearchApiEndpointsDto(query: 'список')))->toBeTrue()
        ->and($matcher->matches($endpoint(), new SearchApiEndpointsDto(query: 'КАТАЛОГ')))->toBeTrue()
        ->and($matcher->matches($endpoint(), new SearchApiEndpointsDto(query: '/api/products')))->toBeTrue()
        ->and($matcher->matches($endpoint(), new SearchApiEndpointsDto(query: 'missing')))->toBeFalse();
});

it('фильтрует по method, tag и authenticated', function () use ($endpoint): void {
    $matcher = new ApiEndpointSearchMatcher();

    expect($matcher->matches(
        $endpoint(),
        new SearchApiEndpointsDto(
            method: HttpMethod::GET,
            group: 'ТОВАРЫ',
            authenticated: false,
        ),
    ))->toBeTrue()
        ->and($matcher->matches(
            $endpoint(),
            new SearchApiEndpointsDto(method: HttpMethod::POST),
        ))->toBeFalse()
        ->and($matcher->matches(
            $endpoint(),
            new SearchApiEndpointsDto(authenticated: true),
        ))->toBeFalse();
});
