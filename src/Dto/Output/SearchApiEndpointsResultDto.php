<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Output;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data as Dto;
use Spatie\LaravelData\DataCollection;

final class SearchApiEndpointsResultDto extends Dto
{
    /** @param DataCollection<int, ApiEndpointSummaryDto> $endpoints */
    public function __construct(
        public ApiDocumentationSourceDto $source,
        #[DataCollectionOf(ApiEndpointSummaryDto::class)]
        public DataCollection $endpoints,
    ) {
    }
}
