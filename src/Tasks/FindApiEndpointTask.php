<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tasks;

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiEndpointDto;

final class FindApiEndpointTask
{
    public function run(ApiDocumentationCatalogDto $catalog, string $identifier): ?ApiEndpointDto
    {
        $identifier = trim($identifier);

        return $catalog->endpoints
            ->toCollection()
            ->first(
                static fn(ApiEndpointDto $endpoint): bool => $endpoint->id === $identifier,
            );
    }
}
