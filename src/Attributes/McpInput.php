<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Attributes;

use Attribute;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Laravel\Mcp\Request;
use LogicException;
use ReflectionNamedType;
use ReflectionParameter;
use Spatie\LaravelData\Data as Dto;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class McpInput implements ContextualAttribute
{
    /**
     * @throws BindingResolutionException
     */
    public static function resolve(
        self $attribute,
        Container $container,
        ReflectionParameter $parameter,
    ): Dto {
        unset($attribute);

        $dtoClass = self::dtoClass($parameter);
        $request = $container->make(Request::class);

        return $dtoClass::validateAndCreate($request->all());
    }

    /** @return class-string<Dto> */
    private static function dtoClass(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new LogicException('MCP input parameter must have a DTO class type.');
        }

        $dtoClass = $type->getName();

        if (!is_subclass_of($dtoClass, Dto::class)) {
            throw new LogicException('MCP input parameter must extend ' . Dto::class . '.');
        }

        return $dtoClass;
    }
}
