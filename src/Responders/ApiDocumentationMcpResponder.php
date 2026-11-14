<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Responders;

use AleksandrSpicyn\ScribeMcp\Exceptions\ApiDocumentationException;
use Closure;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Spatie\LaravelData\Data as Dto;
use Throwable;

final readonly class ApiDocumentationMcpResponder
{
    /**
     * @param Closure(): Dto $resolve
     *
     * @throws Throwable
     */
    public function structured(Closure $resolve): Response|ResponseFactory
    {
        try {
            $result = $resolve();
        } catch (ApiDocumentationException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured($result->toArray());
    }
}
