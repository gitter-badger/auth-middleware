<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Support;

use Dakujem\Middleware\FirebaseJwtDecoder;
use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\TokenCallables;
use Dakujem\Middleware\TokenMiddleware;
use Firebase\JWT\JWT;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;

/**
 * AuthFactory - convenience factory.
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
     * @param string $attributeName the decoded token will appear in this attribute; defaults to `token`
     * @param string|null $headerName a header to look for a Bearer token
     * @param string|null $cookieName a cookie to look for a token
     * @return TokenMiddleware
     */
    public function decodeTokens(
        string $attributeName = 'token',
        ?string $headerName = 'Authorization',
        ?string $cookieName = 'token'
    ): MiddlewareInterface {
        if ($this->secret === null) {
            throw new LogicException('Secret not provided.');
        }
        $extractors = [
            $headerName !== null ? TokenMiddleware::headerExtractor($headerName) : null,
            $cookieName !== null ? TokenMiddleware::cookieExtractor($cookieName) : null,
        ];
        return new TokenMiddleware(
            ($this->decoderProvider ?? static::defaultDecoderProvider())($this->secret),
            array_filter($extractors),
            TokenMiddleware::attributeWriter($attributeName)
        );
    }

    /**
     * Create a preconfigured instance of PredicateMiddleware.
     *
     * The MW will check a Request attribute for presence of a decoded token,
     * and call $onError(Request,Response) handler, if no token is not present.
     *
     * @param string $attributeName defaults to `token`
     * @param callable|null $onError a callable with signature fn(Request,Response):?Response
     * @return PredicateMiddleware
     */
    public function assertTokens(string $attributeName = 'token', ?callable $onError = null): MiddlewareInterface
    {
        if ($this->rf === null) {
            throw new LogicException('Response factory not provided.');
        }
        // Create a default/basic responder.
        $responder = PredicateMiddleware::basicErrorResponder($this->rf, 401); // HTTP status 401 (Unauthorized)

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
        return new PredicateMiddleware(
            TokenCallables::attributeTokenProvider($attributeName),
            $responder
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
