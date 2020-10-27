<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Support;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Server\MiddlewareInterface;

/**
 * AuthWizard - friction reducer / convenience helper.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class AuthWizard
{
    public static function factory(?string $secret, ?ResponseFactory $responseFactory): AuthFactory
    {
        return new AuthFactory($secret, $responseFactory);
    }

    public static function decodeTokens(string $secret): MiddlewareInterface
    {
        return static::factory($secret, null)->decodeTokens();
    }

    public static function assertTokens(ResponseFactory $responseFactory): MiddlewareInterface
    {
        return static::factory(null, $responseFactory)->assertTokens();
    }
}
