<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Actions;

use AleksandrSpicyn\ScribeMcp\Dto\Input\SearchApiEndpointsDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\SearchApiEndpointsResultDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Tasks\GetApiDocumentationCatalogTask;
use AleksandrSpicyn\ScribeMcp\Tasks\SearchApiEndpointsTask;

final class SearchApiEndpointsAction
{
    public function __construct(
        private readonly GetApiDocumentationCatalogTask $catalogTask,
        private readonly SearchApiEndpointsTask $searchTask,
    ) {
    }

    /** @throws ApiDocumentationUnavailableException */
    public function run(SearchApiEndpointsDto $input): SearchApiEndpointsResultDto
    {
        $catalog = $this->catalogTask->run();

        return new SearchApiEndpointsResultDto(
            source: $catalog->source,
            endpoints: $this->searchTask->run($catalog, $input),
        );
    }
}
