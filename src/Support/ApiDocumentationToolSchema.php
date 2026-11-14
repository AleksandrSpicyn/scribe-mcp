<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Support;

use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;

final readonly class ApiDocumentationToolSchema
{
    /** @return array<string, Type> */
    public function searchResult(JsonSchema $schema): array
    {
        return [
            'source' => $this->source($schema)->required(),
            'endpoints' => $schema->array()
                ->items($this->endpointSummary($schema))
                ->required(),
        ];
    }

    /** @return array<string, Type> */
    public function endpointResult(JsonSchema $schema): array
    {
        return [
            'source' => $this->source($schema)->required(),
            'endpoint' => $this->endpoint($schema)->required(),
            'document' => $schema->object()->required(),
        ];
    }

    private function source(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'artifact' => $schema->string()->min(1)->required(),
            'openapi_version' => $schema->string()
                ->pattern('^3\.\d+\.\d+$')
                ->required(),
            'fingerprint' => $schema->string()
                ->pattern('^[a-f0-9]{64}$')
                ->required(),
            'endpoint_count' => $schema->integer()
                ->min(0)
                ->required(),
        ])->withoutAdditionalProperties();
    }

    private function endpointSummary(JsonSchema $schema): ObjectType
    {
        return $schema->object($this->endpointSummaryProperties($schema))
            ->withoutAdditionalProperties();
    }

    private function endpoint(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            ...$this->endpointSummaryProperties($schema),
            'operation' => $schema->object()->required(),
            'path_item' => $schema->object()->required(),
        ])->withoutAdditionalProperties();
    }

    /** @return array<string, Type> */
    private function endpointSummaryProperties(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->min(1)->required(),
            'method' => $schema->string()
                ->enum(HttpMethod::class)
                ->required(),
            'path' => $schema->string()->min(1)->required(),
            'summary' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'tags' => $schema->array()
                ->items($schema->string())
                ->required(),
            'authenticated' => $schema->boolean()->required(),
            'has_request_body' => $schema->boolean()->required(),
            'response_statuses' => $schema->array()
                ->items($schema->anyOf([
                    $schema->integer()->min(100)->max(599),
                    $schema->string()->enum([
                        'default',
                        '1XX',
                        '2XX',
                        '3XX',
                        '4XX',
                        '5XX',
                    ]),
                ]))
                ->unique()
                ->required(),
        ];
    }
}
