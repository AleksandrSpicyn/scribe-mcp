<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Output;

use AleksandrSpicyn\ScribeMcp\Transformers\OpenApiObjectTransformer;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data as Dto;

final class GetApiEndpointResultDto extends Dto
{
    /** @param Collection<string, mixed> $document */
    public function __construct(
        public ApiDocumentationSourceDto $source,
        public ApiEndpointDto $endpoint,
        #[WithTransformer(OpenApiObjectTransformer::class)]
        public Collection $document,
    ) {
    }
}
