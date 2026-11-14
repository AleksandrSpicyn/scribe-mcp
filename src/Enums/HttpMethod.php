<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Enums;

enum HttpMethod: string
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
    case TRACE = 'TRACE';
}
