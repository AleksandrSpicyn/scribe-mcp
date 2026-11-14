<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Exceptions;

final class ApiEndpointNotFoundException extends ApiDocumentationException
{
    public function __construct()
    {
        parent::__construct('Endpoint не найден. Сначала используйте search-api-endpoints.');
    }
}
