<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Output;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data as Dto;

final class ApiDocumentationSourceDto extends Dto
{
    public function __construct(
        public string $artifact,
        #[MapName('openapi_version')]
        public string $openApiVersion,
        public string $fingerprint,
        #[MapName('endpoint_count')]
        public int $endpointCount,
    ) {
    }
}
