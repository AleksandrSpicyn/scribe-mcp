<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tools;

use AleksandrSpicyn\ScribeMcp\Actions\GetApiEndpointAction;
use AleksandrSpicyn\ScribeMcp\Attributes\McpInput;
use AleksandrSpicyn\ScribeMcp\Dto\Input\GetApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\GetApiEndpointResultDto;
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

#[Name('get-api-endpoint')]
#[Description('Возвращает OpenAPI operation без преобразований вместе с path-level и document-level контекстом.')]
#[IsReadOnly]
#[IsIdempotent]
final class GetApiEndpointTool extends Tool
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
        GetApiEndpointDto $dto,
        GetApiEndpointAction $action,
        ApiDocumentationMcpResponder $responder,
    ): Response|ResponseFactory {
        return $responder->structured(static fn(): GetApiEndpointResultDto => $action->run($dto));
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'endpoint_id' => $schema->string()
                ->min(1)
                ->max(1000)
                ->description('Канонический endpoint_id из search-api-endpoints в формате METHOD /path.')
                ->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->toolSchema->endpointResult($schema);
    }
}
