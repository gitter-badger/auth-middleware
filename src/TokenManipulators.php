<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * A helper class providing callables for token manipulation.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class TokenManipulators
{
    /**
     * Create an extractor that extracts Bearer tokens from a header.
     *
     * A valid header must have this format:
     * `Authorization: Bearer <token>`
     * Where "Authorization" is a header name, Bearer is a keyword and <token> is a non-whitespace-only string.
     *
     * @param string $headerName name of the header to extract tokens from
     * @return callable
     */
    public static function headerExtractor(string $headerName = 'Authorization'): callable
    {
        return function (Request $request, ?LoggerInterface $logger = null) use ($headerName): ?string {
            foreach ($request->getHeader($headerName) as $headerValue) {
                $token = static::extractBearerTokenFromHeaderValue($headerValue);
                if ($token !== null && $token !== '') {
                    $logger && $logger->log(LogLevel::DEBUG, "Using Bearer token from request header '{$headerName}'.");
                    return $token;
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
            $token = trim($request->getCookieParams()[$cookieName] ?? '');
            if ($token !== '') {
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
     * Create a writer that writes tokens to a request attribute of choice.
     *
     * @param string $attributeName name of the attribute to write tokens to
     * @param string $errorAttributeName name of the attribute to write error messages to
     * @return callable
     */
    public static function attributeInjector(
        string $attributeName = 'token',
        string $errorAttributeName = 'token.error'
    ): callable {
        return function (
            callable $provider,
            Request $request
        ) use ($attributeName, $errorAttributeName): Request {
            try {
                // Invoke the extraction and decoding process.
                $token = $provider();
            } catch (Throwable $throwable) {
                // Write error messages.
                return $request->withAttribute($errorAttributeName, 'Token error: ' . $throwable->getMessage());
            }
            // Inject the token to the attribute.
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

    /**
     * Extracts a Bearer token from a header value.
     *
     * @param string $headerValue
     * @return string|null an extracted token string or null if not present or header malformed
     */
    public static function extractBearerTokenFromHeaderValue(string $headerValue): ?string
    {
        $matches = null;
        if ($headerValue !== '' && preg_match('/^Bearer\s+(\S+)\s*$/i', $headerValue, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
