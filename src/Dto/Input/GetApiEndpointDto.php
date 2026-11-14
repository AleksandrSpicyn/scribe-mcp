<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Input;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data as Dto;

final class GetApiEndpointDto extends Dto
{
    public function __construct(
        #[MapName('endpoint_id'), Required, StringType, Max(1000)]
        public string $endpointId,
    ) {
    }
}
