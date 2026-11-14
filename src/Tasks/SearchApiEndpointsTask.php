<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tasks;

use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointSummaryDto;
use AleksandrSpicyn\ScribeMcp\Support\ApiEndpointSearchMatcher;
use Illuminate\Container\Attributes\Config;
use Spatie\LaravelData\DataCollection;

final class SearchApiEndpointsTask
{
    public function __construct(
        private readonly ApiEndpointSearchMatcher $matcher,
        #[Config('scribe-mcp.search_limit', 50)]
        private readonly int $maxResults,
    ) {
    }

    /** @return DataCollection<int, ApiEndpointSummaryDto> */
    public function run(
        ApiDocumentationCatalogDto $catalog,
        SearchApiEndpointsDto $input,
    ): DataCollection {
        $limit = min($input->limit, $this->maxResults);

        $results = $catalog->endpoints
            ->toCollection()
            ->filter(fn(ApiEndpointDto $endpoint): bool => $this->matcher->matches($endpoint, $input))
            ->take($limit)
            ->map(fn(ApiEndpointDto $endpoint): ApiEndpointSummaryDto => $this->summary($endpoint))
            ->values();

        return new DataCollection(ApiEndpointSummaryDto::class, $results);
    }

    private function summary(ApiEndpointDto $endpoint): ApiEndpointSummaryDto
    {
        return new ApiEndpointSummaryDto(
            id: $endpoint->id,
            method: $endpoint->method,
            path: $endpoint->path,
            summary: $endpoint->summary,
            description: $endpoint->description,
            tags: $endpoint->tags,
            authenticated: $endpoint->authenticated,
            hasRequestBody: $endpoint->hasRequestBody,
            responseStatuses: $endpoint->responseStatuses,
        );
    }
}
