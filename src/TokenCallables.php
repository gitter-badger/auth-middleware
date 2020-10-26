<?php

declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * A helper class providing callables for token manipulation.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class TokenCallables
{
    /**
     * Create a provider that provides tokens from a request attribute of choice.
     *
     * @param string $attributeName
     * @return callable
     */
    public static function attributeTokenProvider(string $attributeName = 'token'): callable
    {
        return function (Request $request) use ($attributeName): ?object {
            return $request->getAttribute($attributeName);
        };
    }
}
