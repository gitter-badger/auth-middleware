<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Factory;

use Dakujem\Middleware\FirebaseJwtDecoder;
use Dakujem\Middleware\GenericMiddleware;
use Dakujem\Middleware\TokenManipulators as Man;
use Dakujem\Middleware\TokenMiddleware;
use Firebase\JWT\JWT;
use Generator;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface as Logger;

/**
 * AuthFactory - convenience middleware factory.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class AuthFactory
{
    /** @var callable|null fn(string):callable */
    protected $decoderFactory;
    protected ?ResponseFactory $responseFactory;

    public function __construct(
        ?callable $decoderFactory,
        ?ResponseFactory $responseFactory
    ) {
        $this->decoderFactory = $decoderFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Creates a preconfigured instance of TokenMiddleware.
     *
     * By default, the MW extracts tokens from `Authorization` header or `token` cookie,
     * then decodes them using Firebase JWT decoder,
     * and finally writes the decoded tokens to the `token` attribute of the request.
     *
     * @param string|null $tokenAttribute the decoded token will appear in this attribute; defaults to `token`
     * @param string|null $headerName a header to look for a Bearer token; header detection not used when `null`
     * @param string|null $cookieName a cookie to look for a token; cookie detection not used when `null`
     * @param string|null $errorAttribute an error message will appear here; defaults to `{$tokenAttribute}.error`
     * @param Logger|null $logger
     * @return TokenMiddleware
     */
    public function decodeTokens(
        ?string $tokenAttribute = null,
        ?string $headerName = Man::HEADER_NAME,
        ?string $cookieName = Man::COOKIE_NAME,
        ?string $errorAttribute = null,
        ?Logger $logger = null
    ): MiddlewareInterface {
        if ($this->decoderFactory === null) {
            throw new LogicException('Decoder factory not provided.');
        }
        return new TokenMiddleware(
            ($this->decoderFactory)(),
            (function () use ($headerName, $cookieName): Generator {
                $headerName !== null && yield Man::headerExtractor($headerName);
                $cookieName !== null && yield Man::cookieExtractor($cookieName);
            })(),
            Man::attributeInjector(
                $tokenAttribute ?? Man::TOKEN_ATTRIBUTE_NAME,
                $errorAttribute ?? (($tokenAttribute ?? Man::TOKEN_ATTRIBUTE_NAME) . Man::ERROR_ATTRIBUTE_SUFFIX)
            ),
            $logger
        );
    }

    /**
     * Create an instance of middleware that asserts the presence of decoded tokens
     * in a request attribute of choice.
     *
     * The MW will check the token Request attribute for presence of a decoded token,
     * and return a 401 Response when it is not present.
     * Error messages are read from the error Request attribute.
     *
     * @param string|null $tokenAttribute name of the token-containing attribute, defaults to `token`
     * @param string|null $errorAttribute name of the error-containing attribute, defaults to `{$tokenAttribute}.error`
     * @return GenericMiddleware
     */
    public function assertTokens(
        ?string $tokenAttribute = null,
        ?string $errorAttribute = null
    ): MiddlewareInterface {
        return $this->inspectTokens(
            fn($token, callable $next) => $next(),
            $tokenAttribute,
            $errorAttribute
        );
    }

    /**
     * Create an instance of middleware that inspects tokens present in an attribute
     * and calls the next middleware for valid tokens
     * or returns an error response for invalid tokens and when tokens are missing.
     *
     * The "inspector" receives a decoded token
     * and decides whether to invoke the next middleware
     * or return an error response.
     *
     * An example "inspector":
     * ```
     *   function (object $token, callable $next, callable $withError): Response {
     *       if ($token->sub === 42) {        // Implement your inspection logic here.
     *           return $next();              // Invoke the next middleware for valid tokens
     *       }                                // or
     *       return $withError('Bad token.'); // return an error response for invalid ones.
     *   }
     * ```
     * Note:
     *   $withError returns a fresh 401 Response upon invocation, but you need not use it.
     *   When invoked with an argument, it encodes it as JSON and sets the Content-type header.
     *
     * @param callable $inspector fn(Token,callable Next,callable WithError):Response
     * @param string|null $tokenAttribute name of the token-containing attribute, defaults to `token`
     * @param string|null $errorAttribute name of the error-containing attribute, defaults to `{$tokenAttribute}.error`
     * @return GenericMiddleware
     */
    public function inspectTokens(
        callable $inspector, // fn(Token,callable,callable):Response
        ?string $tokenAttribute = null,
        ?string $errorAttribute = null
    ): MiddlewareInterface {
        if ($this->responseFactory === null) {
            throw new LogicException('Response factory not provided.');
        }
        return Man::callableToMiddleware(function (
            Request $request,
            Handler $next
        ) use (
            $inspector,
            $tokenAttribute,
            $errorAttribute
        ): Response {
            $response = fn() => $this->responseFactory->createResponse(401); // HTTP status 401 (Unauthorized)
            $withError = fn($error = null) => $error !== null ? Man::writeJsonError($response(), $error) : $response();
            $token = $request->getAttribute($tokenAttribute ?? Man::TOKEN_ATTRIBUTE_NAME);
            if (is_object($token)) { // asserts that the token is an object
                return $inspector(
                    $token,
                    fn() => $next->handle($request),
                    $withError
                );
            }
            // When the token is `null`, read the error message attribute
            return $withError(
                $request->getAttribute(
                    $errorAttribute ?? (($tokenAttribute ?? Man::TOKEN_ATTRIBUTE_NAME) . Man::ERROR_ATTRIBUTE_SUFFIX)
                ) ?? 'No valid token found.'
            );
        });
    }

    /**
     * Creates a default decoder factory.
     * The factory can be used for the constructor.
     *
     * @param string $secret secret key for JWT decoder
     * @return callable fn():FirebaseJwtDecoder
     */
    public static function defaultDecoderFactory(string $secret): callable
    {
        if (!class_exists(JWT::class)) {
            throw new LogicException(
                'Firebase JWT is not installed. ' .
                'Requires firebase/php-jwt package (`composer require firebase/php-jwt:"^5.0"`).'
            );
        }
        return fn(): FirebaseJwtDecoder => new FirebaseJwtDecoder($secret);
    }
}
