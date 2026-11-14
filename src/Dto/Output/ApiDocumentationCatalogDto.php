<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Output;

use AleksandrSpicyn\ScribeMcp\Transformers\OpenApiObjectTransformer;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data as Dto;
use Spatie\LaravelData\DataCollection;

final class ApiDocumentationCatalogDto extends Dto
{
    /**
     * @param DataCollection<int, ApiEndpointDto> $endpoints
     * @param Collection<string, mixed> $document
     */
    public function __construct(
        public ApiDocumentationSourceDto $source,
        #[DataCollectionOf(ApiEndpointDto::class)]
        public DataCollection $endpoints,
        #[WithTransformer(OpenApiObjectTransformer::class)]
        public Collection $document,
    ) {
    }
}
