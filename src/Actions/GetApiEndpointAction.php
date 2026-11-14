<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Actions;

use AleksandrSpicyn\ScribeMcp\Dto\Input\GetApiEndpointDto;
use AleksandrSpicyn\ScribeMcp\Dto\Output\GetApiEndpointResultDto;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationUnavailableException;
use AleksandrSpicyn\ScribeMcp\Exceptions\ApiEndpointNotFoundException;
use AleksandrSpicyn\ScribeMcp\Tasks\FindApiEndpointTask;
use AleksandrSpicyn\ScribeMcp\Tasks\GetApiDocumentationCatalogTask;

final class GetApiEndpointAction
{
    public function __construct(
        private readonly GetApiDocumentationCatalogTask $catalogTask,
        private readonly FindApiEndpointTask $findTask,
    ) {
    }

    /**
     * @throws ApiEndpointNotFoundException
     * @throws ApiDocumentationUnavailableException
     */
    public function run(GetApiEndpointDto $input): GetApiEndpointResultDto
    {
        $catalog = $this->catalogTask->run();
        $endpoint = $this->findTask->run($catalog, $input->endpointId)
            ?? throw new ApiEndpointNotFoundException();

        return new GetApiEndpointResultDto(
            source: $catalog->source,
            endpoint: $endpoint,
            document: $catalog->document,
        );
    }
}
