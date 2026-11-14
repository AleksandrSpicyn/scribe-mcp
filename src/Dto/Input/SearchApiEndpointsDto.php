<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Dto\Input;

use AleksandrSpicyn\ScribeMcp\Enums\HttpMethod;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Enum as EnumRule;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data as Dto;

final class SearchApiEndpointsDto extends Dto
{
    public function __construct(
        #[Nullable, StringType, Max(500)]
        public ?string $query = null,
        #[Nullable, EnumRule(HttpMethod::class)]
        public ?HttpMethod $method = null,
        #[Nullable, StringType, Max(255)]
        public ?string $group = null,
        #[Nullable, BooleanType]
        public ?bool $authenticated = null,
        #[IntegerType, Min(1), Max(50)]
        public int $limit = 20,
    ) {
    }
}
