<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Output;

use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data as Dto;

final class ApiEndpointSummaryDto extends Dto
{
    /**
     * @param Collection<int, string> $tags
     * @param Collection<int, int|string> $responseStatuses
     */
    public function __construct(
        public string $id,
        public HttpMethod $method,
        public string $path,
        public string $summary,
        public string $description,
        public Collection $tags,
        public bool $authenticated,
        #[MapName('has_request_body')]
        public bool $hasRequestBody,
        #[MapName('response_statuses')]
        public Collection $responseStatuses,
    ) {
    }
}
