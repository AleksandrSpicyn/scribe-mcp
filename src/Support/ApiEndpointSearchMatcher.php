<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Support;

use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;

final readonly class ApiEndpointSearchMatcher
{
    public function matches(
        ApiEndpointDto $endpoint,
        SearchApiEndpointsDto $input,
    ): bool {
        $query = $this->lowercase(trim($input->query ?? ''));
        $group = $this->lowercase(trim($input->group ?? ''));
        $haystack = mb_strtolower(implode(' ', [
            $endpoint->id,
            $endpoint->path,
            $endpoint->summary,
            $endpoint->description,
            $endpoint->tags->implode(' '),
        ]), 'UTF-8');

        return !in_array(false, [
            $input->method === null || $endpoint->method === $input->method,
            $group === '' || $endpoint->tags->contains(
                static fn(string $tag): bool => mb_strtolower($tag, 'UTF-8') === $group,
            ),
            $input->authenticated === null || $endpoint->authenticated === $input->authenticated,
            $query === '' || str_contains($haystack, $query),
        ], true);
    }

    private function lowercase(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
