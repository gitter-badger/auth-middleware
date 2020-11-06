<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * A helper class providing callables for token manipulation.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class TokenManipulators
{
    public const TOKEN_ATTRIBUTE_NAME = 'token';
    public const HEADER_NAME = 'Authorization';
    public const COOKIE_NAME = 'token';
    public const ERROR_ATTRIBUTE_NAME = self::TOKEN_ATTRIBUTE_NAME . self::ERROR_ATTRIBUTE_SUFFIX;
    public const ERROR_ATTRIBUTE_SUFFIX = '.error';

    /**
     * Create an extractor that extracts Bearer tokens from a header of choice.
     *
     * A valid header must have this format:
     * `Authorization: Bearer <token>`
     * Where "Authorization" is a header name, Bearer is a keyword and <token> is a non-whitespace-only string.
     *
     * @param string $headerName name of the header to extract tokens from
     * @return callable
     */
    public static function headerExtractor(string $headerName = self::HEADER_NAME): callable
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
     * Create an extractor that extracts tokens from a cookie of choice.
     *
     * @param string $cookieName name of the cookie to extract tokens from
     * @return callable
     */
    public static function cookieExtractor(string $cookieName = self::COOKIE_NAME): callable
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
     * Create an extractor that extracts tokens from a request attribute of choice.
     *
     * @param string $attributeName
     * @return callable
     */
    public static function attributeExtractor(string $attributeName = self::TOKEN_ATTRIBUTE_NAME): callable
    {
        return function (Request $request) use ($attributeName): ?string {
            return $request->getAttribute($attributeName);
        };
    }

    /**
     * Create an injector that writes tokens to a request attribute of choice.
     *
     * All `RuntimeException`s are caught and converted to error messages.
     * Error messages are written to the other attribute of choice.
     *
     * @param string $attributeName name of the attribute to write tokens to
     * @param string $errorAttributeName name of the attribute to write error messages to
     * @return callable
     */
    public static function attributeInjector(
        string $attributeName = self::TOKEN_ATTRIBUTE_NAME,
        string $errorAttributeName = self::ERROR_ATTRIBUTE_NAME
    ): callable {
        return function (
            callable $provider,
            Request $request
        ) use ($attributeName, $errorAttributeName): Request {
            try {
                // Invoke the extraction and decoding process.
                $token = $provider();
            } catch (RuntimeException $exception) {
                // Write error messages.
                return $request->withAttribute($errorAttributeName, 'Token error: ' . $exception->getMessage());
            }
            // Inject the token to the attribute.
            return $request->withAttribute($attributeName, $token);
        };
    }

    /**
     * Create a provider that returns _decoded tokens_ from a request attribute of choice.
     *
     * @param string $attributeName
     * @return callable
     */
    public static function attributeTokenProvider(string $attributeName = self::TOKEN_ATTRIBUTE_NAME): callable
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
     * Create a function that reads a message from a Request attribute of choice
     * and writes it as JSON to the Response body, setting correct content-type header.
     *
     * It can be used to transfer an error message from the Request to the Response for end user convenience.
     * Warning: Opinionated and inflexible. You will probably want to use your own implementation.
     *
     * @param string $errorAttributeName
     * @return callable fn(Request,Response):Response
     */
    public static function errorMessagePassJson(string $errorAttributeName = self::ERROR_ATTRIBUTE_NAME): callable
    {
        return function (Request $request, Response $response) use ($errorAttributeName): Response {
            // When this error handler is called and no error message is found, it assumes that no token was found.
            $msg = $request->getAttribute($errorAttributeName) ?? 'No token found.';
            $stream = $response->getBody();
            /** @noinspection PhpComposerExtensionStubsInspection */
            $stream !== null && $stream->write(json_encode([
                'error' => [
                    'message' => $msg,
                ],
            ]));
            return $response->withHeader('Content-type', 'application/json');
        };
    }

    /**
     * Write error message or error data as JSON to the Response.
     * Also sets the Content-type header for JSON.
     *
     * Warning: Opinionated.
     *
     * @param Response $response
     * @param mixed $error a message or data to be written as error
     * @return Response
     */
    public static function writeJsonError(Response $response, $error): Response
    {
        $stream = $response->getBody();
        /** @noinspection PhpComposerExtensionStubsInspection */
        $stream !== null && $stream->write(json_encode([
            'error' => is_string($error) ? ['message' => $error] : $error,
        ]));
        return $response->withHeader('Content-type', 'application/json');
    }

    /**
     * Turn any callable with signature `fn(Request):Response` into a PSR `RequestHandlerInterface` implementation.
     * If the callable is a handler already, it is returned directly.
     *
     * @param callable $callable fn(Request):Response
     * @return Handler
     */
    public static function callableToHandler(callable $callable): Handler
    {
        return !$callable instanceof Handler ? new GenericHandler($callable) : $callable;
    }

    /**
     * Turn any callable with signature `fn(Request,Handler):Response` into a PSR `MiddlewareInterface` implementation.
     * If the callable is a middleware already, it is returned directly.
     *
     * @param callable $callable fn(Request,Handler):Response
     * @return Middleware
     */
    public static function callableToMiddleware(callable $callable): Middleware
    {
        return !$callable instanceof Middleware ? new GenericMiddleware($callable) : $callable;
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
