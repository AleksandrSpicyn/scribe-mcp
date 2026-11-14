<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Servers;

use AleksandrSpicyn\ScribeMcp\Tools\GetApiEndpointTool;
use AleksandrSpicyn\ScribeMcp\Tools\SearchApiEndpointsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

#[Name('Scribe API Documentation')]
#[Version('1.0.0')]
#[Instructions('Сначала вызовите search-api-endpoints -> get-api-endpoint. Сервер только читает сгенерированный Scribe OpenAPI и никогда не выполняет API-запросы.')]
final class ApiDocumentationServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        SearchApiEndpointsTool::class,
        GetApiEndpointTool::class,
    ];

    /** @var array<int, class-string<Resource>> */
    protected array $resources = [];

    protected array $prompts = [];
}
