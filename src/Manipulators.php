<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * A helper class providing callables for token manipulation.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class Manipulators
{
    /**
     * Create an extractor that extracts Bearer tokens from a header.
     *
     * @param string $headerName name of the header to extract tokens from
     * @return callable
     */
    public static function headerExtractor(string $headerName = 'Authorization'): callable
    {
        return function (Request $request, ?LoggerInterface $logger = null) use ($headerName): ?string {
            $headerValues = $request->getHeader($headerName);
            foreach (array_filter($headerValues) as $header) {
                $matches = null;
                if (preg_match('/^Bearer\s+(.*)$/i', $header, $matches)) {
                    $logger && $logger->log(LogLevel::DEBUG, "Using Bearer token from request header '{$headerName}'.");
                    return $matches[1];
                }
                $logger && $logger->log(LogLevel::DEBUG, "Bearer token not present in request header '{$headerName}'.");
            }
            return null;
        };
    }

    /**
     * Create an extractor that extracts tokens from a cookie.
     *
     * @param string $cookieName name of the cookie to extract tokens from
     * @return callable
     */
    public static function cookieExtractor(string $cookieName = 'token'): callable
    {
        return function (Request $request, ?LoggerInterface $logger = null) use ($cookieName): ?string {
            $token = $request->getCookieParams()[$cookieName] ?? null;
            if ($token) {
                $logger && $logger->log(LogLevel::DEBUG, "Using bare token from cookie '{$cookieName}'.");
                return $token;
            }
            return null;
        };
    }

    /**
     * Create a writer that writes tokens to a request attribute of choice.
     *
     * @param string $attributeName name of the attribute to write tokens to
     * @return callable
     */
    public static function attributeWriter(string $attributeName = 'token'): callable
    {
        return function (?object $token, Request $request) use ($attributeName): Request {
            return $request->withAttribute($attributeName, $token);
        };
    }

    /**
     * Create a provider that provides decoded tokens from a request attribute of choice.
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

    /**
     * Create a basic error responder that returns a response with 400 (Bad Request) status.
     *
     * @param ResponseFactory $responseFactory
     * @param int|null $httpStatus HTTP response status, default is 400
     * @return callable
     */
    public static function basicErrorResponder(
        ResponseFactory $responseFactory,
        ?int $httpStatus = null
    ): callable {
        return function (/* Request $request */) use ($responseFactory, $httpStatus): Response {
            return $responseFactory->createResponse($httpStatus ?? 400);
        };
    }

    /**
     * Turn any callable with signature `fn(Request):Response` into a PSR `RequestHandlerInterface` implementation.
     * If the callable is a handler already, it is returned directly.
     *
     * @param callable $callable
     * @return Handler
     */
    public static function callableToHandler(callable $callable): Handler
    {
        return !$callable instanceof Handler ? new class($callable) implements Handler {
            private $callable;

            public function __construct(callable $callable)
            {
                $this->callable = $callable;
            }

            public function handle(Request $request): Response
            {
                return ($this->callable)($request);
            }
        } : $callable;
    }
}
