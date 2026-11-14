<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Transformers;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;
use stdClass;

final readonly class OpenApiObjectTransformer implements Transformer
{
    /** @return array<string, mixed>|stdClass */
    public function transform(
        DataProperty $property,
        mixed $value,
        TransformationContext $context,
    ): array|stdClass {
        if (!$value instanceof Collection || $value->isEmpty()) {
            return new stdClass();
        }

        return $value->toArray();
    }
}
