<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Integrations\Scribe;

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationSourceDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;
use stdClass;

final readonly class ScribeOpenApiCatalogFactory
{
    /** @param list<string> $pathPrefixes */
    public function __construct(
        private ScribeOpenApiPathItemResolver $pathItemResolver = new ScribeOpenApiPathItemResolver(),
        #[Config('scribe-mcp.path_prefixes', [])]
        private array $pathPrefixes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $document
     *
     * @throws ApiDocumentationUnavailableException
     */
    public function make(
        array $document,
        string $artifact,
        string $fingerprint,
    ): ApiDocumentationCatalogDto {
        $paths = $document['paths'] ?? [];

        if (!is_array($paths)) {
            $paths = [];
        }

        /** @var array<string, mixed> $paths */
        $documentContext = collect($document)->except('paths');
        $endpoints = collect($paths)
            ->map(fn(mixed $pathItem): ?array => $this->object($pathItem))
            ->filter(fn(?array $pathItem, string $path): bool => $pathItem !== null && $this->documentedPath($path))
            ->flatMap(
                fn(array $pathItem, string $path): Collection => $this->endpoints(
                    path: $path,
                    resolvedPathItem: $this->pathItemResolver->resolve($pathItem, $document),
                    document: $documentContext,
                ),
            )
            ->sortBy(static fn(ApiEndpointDto $endpoint): string => $endpoint->id)
            ->values();

        return new ApiDocumentationCatalogDto(
            source: new ApiDocumentationSourceDto(
                artifact: $artifact,
                openApiVersion: $document['openapi'],
                fingerprint: $fingerprint,
                endpointCount: $endpoints->count(),
            ),
            endpoints: new DataCollection(ApiEndpointDto::class, $endpoints),
            document: $documentContext,
        );
    }

    /**
     * @param array<string, mixed> $resolvedPathItem
     * @param Collection<string, mixed> $document
     *
     * @return Collection<int, ApiEndpointDto>
     */
    private function endpoints(
        string $path,
        array $resolvedPathItem,
        Collection $document,
    ): Collection {
        $pathContext = collect($resolvedPathItem)->except($this->operationKeys());

        return collect(HttpMethod::cases())
            ->map(function (HttpMethod $method) use (
                $document,
                $path,
                $pathContext,
                $resolvedPathItem,
            ): ?ApiEndpointDto {
                $operation = $this->object($resolvedPathItem[strtolower($method->value)] ?? null);

                if ($operation === null) {
                    return null;
                }

                return $this->endpoint(
                    method: $method,
                    path: $path,
                    operation: $operation,
                    pathItem: $pathContext,
                    document: $document,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param array<string, mixed> $operation
     * @param Collection<string, mixed> $pathItem
     * @param Collection<string, mixed> $document
     */
    private function endpoint(
        HttpMethod $method,
        string $path,
        array $operation,
        Collection $pathItem,
        Collection $document,
    ): ApiEndpointDto {
        return new ApiEndpointDto(
            id: $method->value . ' ' . $path,
            method: $method,
            path: $path,
            summary: $this->string($operation, 'summary'),
            description: $this->string($operation, 'description'),
            tags: $this->strings($operation['tags'] ?? null),
            authenticated: $this->authenticated($operation, $document),
            hasRequestBody: is_array($operation['requestBody'] ?? null)
                || ($operation['requestBody'] ?? null) instanceof stdClass,
            responseStatuses: $this->responseStatuses($operation),
            operation: collect($operation),
            pathItem: $pathItem,
        );
    }

    /** @param array<string, mixed> $operation */
    private function string(array $operation, string $key): string
    {
        return is_string($operation[$key] ?? null) ? $operation[$key] : '';
    }

    /** @return Collection<int, string> */
    private function strings(mixed $values): Collection
    {
        if (!is_array($values)) {
            return collect();
        }

        return collect($values)
            ->filter(static fn(mixed $value): bool => is_string($value))
            ->values();
    }

    /**
     * @param array<string, mixed> $operation
     * @param Collection<string, mixed> $document
     */
    private function authenticated(array $operation, Collection $document): bool
    {
        $security = array_key_exists('security', $operation)
            ? $operation['security']
            : $document->get('security', []);

        if (!is_array($security) || $security === []) {
            return false;
        }

        $requirements = collect($security)
            ->filter(
                static fn(mixed $requirement): bool => is_array($requirement)
                    || $requirement instanceof stdClass,
            );

        return $requirements->isNotEmpty()
            && $requirements->doesntContain(
                static fn(array|stdClass $requirement): bool => (array)$requirement === [],
            );
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return Collection<int, int|string>
     */
    private function responseStatuses(array $operation): Collection
    {
        $responses = $operation['responses'] ?? null;
        $responses = $this->object($responses);

        if ($responses === null) {
            return collect();
        }

        /** @var Collection<int, int|string> $statuses */
        $statuses = collect(array_keys($responses))
            ->filter($this->isResponseStatus(...))
            ->values();

        return $statuses;
    }

    private function isResponseStatus(int|string $status): bool
    {
        if (is_int($status)) {
            return $status >= 100 && $status <= 599;
        }

        if (ctype_digit($status)) {
            $numericStatus = (int)$status;

            return strlen($status) === 3
                && $numericStatus >= 100
                && $numericStatus <= 599;
        }

        return in_array($status, [
            'default',
            '1XX',
            '2XX',
            '3XX',
            '4XX',
            '5XX',
        ], true);
    }

    /** @return list<string> */
    private function operationKeys(): array
    {
        return collect(HttpMethod::cases())
            ->map(static fn(HttpMethod $method): string => strtolower($method->value))
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function object(mixed $value): ?array
    {
        if ($value instanceof stdClass) {
            return get_object_vars($value);
        }

        return is_array($value) ? $value : null;
    }

    private function documentedPath(string $path): bool
    {
        return $this->pathPrefixes === []
            || collect($this->pathPrefixes)->contains(
                static fn(string $prefix): bool => str_starts_with($path, $prefix),
            );
    }
}
