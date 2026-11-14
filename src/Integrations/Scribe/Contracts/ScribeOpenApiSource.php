<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Integrations\Scribe\Contracts;

use AleksandrSpicyn\ScribeMcp\Dto\Output\ApiDocumentationCatalogDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;

interface ScribeOpenApiSource
{
    /** @throws ApiDocumentationUnavailableException */
    public function read(): ApiDocumentationCatalogDto;
}
