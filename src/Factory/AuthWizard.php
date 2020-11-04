<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Factory;

use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\TokenManipulators as Man;
use Dakujem\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface as Logger;

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
     * @param string|null $attributeName
     * @param string|null $headerName
     * @param string|null $cookieName
     * @param string|null $errorAttributeName
     * @param Logger|null $logger
     * @return TokenMiddleware
     */
    public static function decodeTokens(
        string $secret,
        ?string $attributeName = null,
        ?string $headerName =  Man::HEADER_NAME,
        ?string $cookieName = Man::COOKIE_NAME,
        ?string $errorAttributeName = null,
        ?Logger $logger = null
    ): MiddlewareInterface {
        return static::factory($secret, null)->decodeTokens(
            $attributeName,
            $headerName,
            $cookieName,
            $errorAttributeName,
            $logger
        );
    }

    /**
     * @see AuthFactory::assertTokens()
     *
     * @param ResponseFactory $responseFactory
     * @param string|null $attributeName
     * @param callable|null $onError
     * @return PredicateMiddleware
     */
    public static function assertTokens(
        ResponseFactory $responseFactory,
        ?string $attributeName = null,
        ?callable $onError = null
    ): MiddlewareInterface {
        return static::factory(null, $responseFactory)->assertTokens($attributeName, $onError);
    }

    /**
     * @see AuthFactory::probeTokens()
     *
     * @param ResponseFactory $responseFactory
     * @param callable $probe fn(?object,Request):bool
     * @param string|null $attributeName
     * @param callable|null $onError
     * @return PredicateMiddleware
     */
    public static function probeTokens(
        ResponseFactory $responseFactory,
        callable $probe, // fn(Token):bool
        ?string $attributeName = null,
        ?callable $onError = null
    ): MiddlewareInterface {
        return static::factory(null, $responseFactory)->probeTokens($probe, $attributeName, $onError);
    }
}
