<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Integrations\Scribe;

use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class ScribeOpenApiParser
{
    /**
     * @return array<string, mixed>
     *
     * @throws ApiDocumentationUnavailableException
     */
    public function parse(string $contents): array
    {
        try {
            $parsedDocument = Yaml::parse($contents, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (ParseException $exception) {
            throw new ApiDocumentationUnavailableException(
                'OpenAPI Scribe не удалось разобрать: ' . $exception->getMessage(),
                $exception->getCode(),
                previous: $exception,
            );
        }

        if (!$parsedDocument instanceof stdClass) {
            throw $this->invalidDocument();
        }

        $rawDocument = get_object_vars($parsedDocument);

        if (
            !$this->isOpenApiVersion($rawDocument['openapi'] ?? null)
            || !$this->isInfo($rawDocument['info'] ?? null)
            || !($rawDocument['paths'] ?? null) instanceof stdClass
        ) {
            throw $this->invalidDocument();
        }

        $document = $rawDocument;
        $document['paths'] = get_object_vars($rawDocument['paths']);

        return $document;
    }

    private function isOpenApiVersion(mixed $version): bool
    {
        return is_string($version)
            && preg_match('/^3\.\d+\.\d+$/D', $version) === 1;
    }

    private function isInfo(mixed $info): bool
    {
        if (!$info instanceof stdClass) {
            return false;
        }

        $values = get_object_vars($info);

        return is_string($values['title'] ?? null)
            && $values['title'] !== ''
            && is_string($values['version'] ?? null)
            && $values['version'] !== '';
    }

    private function invalidDocument(): ApiDocumentationUnavailableException
    {
        return new ApiDocumentationUnavailableException(
            'OpenAPI Scribe не содержит корректные openapi, info или object paths.',
        );
    }
}
