<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tools;

use AleksandrSpicyn\ScribeMcp\Actions\SearchApiEndpointsAction;
use AleksandrSpicyn\ScribeMcp\Attributes\McpInput;
use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\SearchApiEndpointsResultDto;
use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use AleksandrSpicyn\ScribeMcp\Responders\ApiDocumentationMcpResponder;
use AleksandrSpicyn\ScribeMcp\Support\ApiDocumentationToolSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;
use Throwable;

#[Name('search-api-endpoints')]
#[Description('Ищет OpenAPI operations по canonical id, path, summary, description и tags.')]
#[IsReadOnly]
#[IsIdempotent]
final class SearchApiEndpointsTool extends Tool
{
    public function __construct(
        private readonly ApiDocumentationToolSchema $toolSchema,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        #[McpInput]
        SearchApiEndpointsDto $dto,
        SearchApiEndpointsAction $action,
        ApiDocumentationMcpResponder $responder,
    ): Response|ResponseFactory {
        return $responder->structured(static fn(): SearchApiEndpointsResultDto => $action->run($dto));
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->max(500)
                ->nullable()
                ->description('Поиск по endpoint id, path, summary, description и tags.'),
            'method' => $schema->string()
                ->enum(HttpMethod::values())
                ->nullable()
                ->description('HTTP-метод.'),
            'group' => $schema->string()
                ->max(255)
                ->nullable()
                ->description('Точный OpenAPI tag без учёта регистра.'),
            'authenticated' => $schema->boolean()
                ->nullable()
                ->description('Фильтр по признаку авторизации endpoint.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(20)
                ->description('Количество результатов, по умолчанию 20.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->toolSchema->searchResult($schema);
    }
}
