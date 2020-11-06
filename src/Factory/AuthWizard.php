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
    /**
     * Create an instance of AuthFactory.
     *
     * @param string|null $secret
     * @param ResponseFactory|null $responseFactory
     * @param mixed ...$args
     * @return AuthFactory
     */
    public static function factory(?string $secret, ?ResponseFactory $responseFactory, ...$args): AuthFactory
    {
        return new AuthFactory($secret, $responseFactory, ...$args);
    }

    /**
     * @see AuthFactory::decodeTokens()
     *
     * @param string $secret API secret key
     * @param string|null $tokenAttribute
     * @param string|null $headerName
     * @param string|null $cookieName
     * @param string|null $errorAttribute
     * @param Logger|null $logger
     * @return TokenMiddleware
     */
    public static function decodeTokens(
        string $secret,
        ?string $tokenAttribute = null,
        ?string $headerName = Man::HEADER_NAME,
        ?string $cookieName = Man::COOKIE_NAME,
        ?string $errorAttribute = null,
        ?Logger $logger = null
    ): MiddlewareInterface {
        return static::factory($secret, null)->decodeTokens(
            $tokenAttribute,
            $headerName,
            $cookieName,
            $errorAttribute,
            $logger
        );
    }

    /**
     * @see AuthFactory::assertTokens()
     *
     * @param ResponseFactory $responseFactory
     * @param string|null $tokenAttribute
     * @param string|null $errorAttribute
     * @return PredicateMiddleware
     */
    public static function assertTokens(
        ResponseFactory $responseFactory,
        ?string $tokenAttribute = null,
        ?string $errorAttribute = null
    ): MiddlewareInterface {
        return static::factory(null, $responseFactory)->assertTokens($tokenAttribute, $errorAttribute);
    }

    /**
     * @see AuthFactory::inspectTokens()
     *
     * @param ResponseFactory $responseFactory
     * @param callable $inspector // fn(Token,callable,callable):Response
     * @param string|null $tokenAttribute
     * @param string|null $errorAttribute
     * @return PredicateMiddleware
     */
    public static function probeTokens(
        ResponseFactory $responseFactory,
        callable $inspector, // fn(Token):bool
        ?string $tokenAttribute = null,
        ?string $errorAttribute = null
    ): MiddlewareInterface {
        return static::factory(null, $responseFactory)->inspectTokens($inspector, $tokenAttribute, $errorAttribute);
    }
}
