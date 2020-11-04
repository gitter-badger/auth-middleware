<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Factory;

use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Server\MiddlewareInterface;

/**
 * AuthWizard - friction reducer / convenience helper.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class AuthWizard
{
    public static function factory(?string $secret, ?ResponseFactory $responseFactory, ...$args): AuthFactory
    {
        return new AuthFactory($secret, $responseFactory, ...$args);
    }

    /**
     * @see AuthFactory::decodeTokens()
     *
     * @param string $secret API secret key
     * @param string $attributeName
     * @param string|null $headerName
     * @param string|null $cookieName
     * @param string|null $errorAttributeName
     * @return TokenMiddleware
     */
    public static function decodeTokens(
        string $secret,
        string $attributeName = 'token',
        ?string $headerName = 'Authorization',
        ?string $cookieName = 'token',
        ?string $errorAttributeName = null
    ): MiddlewareInterface {
        return static::factory($secret, null)->decodeTokens(
            $attributeName,
            $headerName,
            $cookieName,
            $errorAttributeName
        );
    }

    /**
     * @see AuthFactory::assertTokens()
     *
     * @param ResponseFactory $responseFactory
     * @param string $attributeName
     * @param callable|null $onError
     * @return PredicateMiddleware
     */
    public static function assertTokens(
        ResponseFactory $responseFactory,
        string $attributeName = 'token',
        ?callable $onError = null
    ): MiddlewareInterface {
        return static::factory(null, $responseFactory)->assertTokens($attributeName, $onError);
    }
}
