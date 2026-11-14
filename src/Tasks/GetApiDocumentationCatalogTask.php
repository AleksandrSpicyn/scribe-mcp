<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tasks;

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Integrations\Scribe\Contracts\ScribeOpenApiSource;

final class GetApiDocumentationCatalogTask
{
    public function __construct(private readonly ScribeOpenApiSource $source)
    {
    }

    /** @throws ApiDocumentationUnavailableException */
    public function run(): ApiDocumentationCatalogDto
    {
        return $this->source->read();
    }
}
