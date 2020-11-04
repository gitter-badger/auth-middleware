<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Factory;

use Dakujem\Middleware\FirebaseJwtDecoder;
use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\TokenManipulators as Man;
use Dakujem\Middleware\TokenMiddleware;
use Firebase\JWT\JWT;
use Generator;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;

/**
 * AuthFactory - convenience middleware factory.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class AuthFactory
{
    protected ?string $secret;
    protected ?ResponseFactory $rf;
    /** @var callable fn(string):callable */
    protected $decoderProvider;

    public function __construct(?string $secret, ?ResponseFactory $rf, ?callable $defaultDecoderProvider = null)
    {
        $this->secret = $secret;
        $this->rf = $rf;
        $this->decoderProvider = $defaultDecoderProvider;
    }

    /**
     * Creates a preconfigured instance of TokenMiddleware.
     *
     * By default, the MW extracts tokens from `Authorization` header or `token` cookie,
     * then decodes them using Firebase JWT decoder,
     * and finally writes the decoded tokens to the `token` attribute of the request.
     *
     * @param string|null $targetAttributeName the decoded token will appear in this attribute; defaults to `token`
     * @param string|null $headerName a header to look for a Bearer token; header detection not used when `null`
     * @param string|null $cookieName a cookie to look for a token; cookie detection not used when `null`
     * @param string|null $errorAttributeName an error message will appear here; defaults to `token.error`
     * @return TokenMiddleware
     */
    public function decodeTokens(
        ?string $targetAttributeName = null,
        ?string $headerName = null,
        ?string $cookieName = null,
        ?string $errorAttributeName = null
    ): MiddlewareInterface {
        if ($this->secret === null) {
            throw new LogicException('Secret not provided.');
        }
        return new TokenMiddleware(
            ($this->decoderProvider ?? static::defaultDecoderProvider())($this->secret),
            (function () use ($headerName, $cookieName): Generator {
                $headerName !== null && yield Man::headerExtractor($headerName ?? Man::HEADER_NAME);
                $cookieName !== null && yield Man::cookieExtractor($cookieName ?? Man::COOKIE_NAME);
            })(),
            Man::attributeInjector(
                $targetAttributeName ?? Man::TOKEN_ATTRIBUTE_NAME,
                $errorAttributeName ?? (
                    ($targetAttributeName ?? Man::TOKEN_ATTRIBUTE_NAME) . Man::ERROR_ATTRIBUTE_SUFFIX
                )
            )
        );
    }

    /**
     * Create a preconfigured instance of PredicateMiddleware.
     *
     * The MW will check a Request attribute for presence of a decoded token,
     * and call $onError(Request,Response) handler, if no token is not present.
     *
     * @param string|null $attributeName defaults to `token`
     * @param callable|null $onError a callable with signature fn(Request,Response):?Response
     * @return PredicateMiddleware
     */
    public function assertTokens(
        ?string $attributeName = null,
        ?callable $onError = null
    ): MiddlewareInterface {
        return $this->probeTokens(null, $attributeName, $onError);
    }

    /**
     * Create a preconfigured instance of PredicateMiddleware.
     *
     * The "probe" receives a decoded token (or `null`)
     * and decides (by returning a boolean value) whether to allow the next middleware
     * to be executed or the error handler invoked.
     *
     * When no "probe" is passed, the error handler is only called if the token is `null`.
     *
     * @param callable|null $probe a callable probe with signature fn(?object,Request):bool
     * @param string|null $attributeName defaults to `token`
     * @param callable|null $onError a callable with signature fn(Request,Response):?Response
     * @return PredicateMiddleware
     */
    public function probeTokens(
        ?callable $probe, // fn(Token):bool
        ?string $attributeName = null,
        ?callable $onError = null
    ): MiddlewareInterface {
        if ($this->rf === null) {
            throw new LogicException('Response factory not provided.');
        }
        // Create a default/basic responder.
        $responder = Man::basicErrorResponder($this->rf, 401); // HTTP status 401 (Unauthorized)

        // If $onError was passed, create a convenience user responder.
        if ($onError !== null) {
            $responder = function (Request $request) use ($onError, $responder): Response {
                $response = $responder($request);
                // $onError is passed the Request and a fresh 401 Unauthorized Response.
                $rv = $onError($request, $response);
                // $onError can optionally return a Response
                return $rv instanceof Response ? $rv : $response;
            };
        }
        $provider = Man::attributeTokenProvider($attributeName ?? Man::TOKEN_ATTRIBUTE_NAME);
        $predicate = $probe ? function (Request $request) use ($provider, $probe): bool {
            return $probe($provider($request), $request);
        } : $provider;
        return new PredicateMiddleware(
            $predicate,
            Man::callableToHandler($responder)
        );
    }

    protected static function defaultDecoderProvider(): callable
    {
        return function (string $secret) {
            if (!class_exists(JWT::class)) {
                throw new LogicException(
                    'Firebase JWT is not installed. ' .
                    'Require require firebase/php-jwt package (`composer require firebase/php-jwt:"^5.0"`).'
                );
            }
            return new FirebaseJwtDecoder($secret);
        };
    }
}
