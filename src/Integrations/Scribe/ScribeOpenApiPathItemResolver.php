<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Integrations\Scribe;

use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use stdClass;

final readonly class ScribeOpenApiPathItemResolver
{
    /**
     * @param array<string, mixed> $pathItem
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     *
     * @throws ApiDocumentationUnavailableException
     */
    public function resolve(array $pathItem, array $document): array
    {
        return $this->resolvePathItem($pathItem, $document, []);
    }

    /**
     * @param array<string, mixed> $pathItem
     * @param array<string, mixed> $document
     * @param array<string, true> $visitedReferences
     *
     * @return array<string, mixed>
     *
     * @throws ApiDocumentationUnavailableException
     */
    private function resolvePathItem(
        array $pathItem,
        array $document,
        array $visitedReferences,
    ): array {
        $reference = $pathItem['$ref'] ?? null;

        if ($reference === null) {
            return $pathItem;
        }

        if (!is_string($reference) || !str_starts_with($reference, '#/')) {
            throw new ApiDocumentationUnavailableException(
                'Внешний Path Item $ref не поддерживается: ' . (is_string($reference) ? $reference : 'invalid'),
            );
        }

        if (isset($visitedReferences[$reference])) {
            throw new ApiDocumentationUnavailableException('Обнаружен циклический Path Item $ref: ' . $reference);
        }

        $target = $this->resolveLocalReference($reference, $document);

        if ($target instanceof stdClass) {
            $target = get_object_vars($target);
        }

        if (!is_array($target)) {
            throw new ApiDocumentationUnavailableException('Path Item $ref указывает не на object: ' . $reference);
        }

        $visitedReferences[$reference] = true;
        $resolvedPathItem = $this->resolvePathItem($target, $document, $visitedReferences);

        return array_replace($resolvedPathItem, $pathItem);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @throws ApiDocumentationUnavailableException
     */
    private function resolveLocalReference(string $reference, array $document): mixed
    {
        $target = $document;
        $segments = explode('/', substr($reference, 2));

        foreach ($segments as $segment) {
            $key = strtr(rawurldecode($segment), [
                '~1' => '/',
                '~0' => '~',
            ]);

            if ($target instanceof stdClass) {
                $target = get_object_vars($target);
            }

            if (!is_array($target) || !array_key_exists($key, $target)) {
                throw new ApiDocumentationUnavailableException('Path Item $ref не найден: ' . $reference);
            }

            $target = $target[$key];
        }

        return $target;
    }
}
