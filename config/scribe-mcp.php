<?php

declare(strict_types=1);

return [
    'openapi_path' => env('SCRIBE_MCP_OPENAPI_PATH', storage_path('app/scribe/openapi.yaml')),
    'search_limit' => 50,
    'path_prefixes' => [],
];
