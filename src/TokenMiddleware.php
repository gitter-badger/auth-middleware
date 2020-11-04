<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Generator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * This middleware detects tokens in the request and decodes them.
 * Its responsibility is to provide tokens for the application.
 *
 * By default, it extracts raw tokens from `Authorization` header or `token` cookie,
 * then decodes them as JWT (JSON Web Token) using the provided decoder,
 * and finally writes the decoded tokens to the `token` attribute of the request,
 * or writes an error message to `token.error` attribute in case the decoding fails.
 * All steps are configurable.
 *
 * Uses a set of extractors to extract a raw token string,
 * a decoder to decode it to a token representation
 * and an injector to write the token (or error message) to a request attribute.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class TokenMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $decoder;
    /** @var callable[] */
    private iterable $extractors;
    /** @var callable */
    private $injector;
    private ?LoggerInterface $logger;

    public function __construct(
        callable $decoder,
        ?iterable $extractors = null,
        ?callable $injector = null,
        ?LoggerInterface $logger = null
    ) {
        $this->decoder = $decoder;
        $this->extractors = $extractors ?? (function (): Generator {
                yield TokenManipulators::headerExtractor();
                yield TokenManipulators::cookieExtractor();
            })();
        $this->injector = $injector ?? TokenManipulators::attributeInjector();
        $this->logger = $logger;
    }

    /**
     * PSR-15 middleware processing.
     *
     * @param Request $request
     * @param Handler $next
     * @return Response
     */
    public function process(Request $request, Handler $next): Response
    {
        return $next->handle(
            $this->injectRequest(
                $request,
                fn(): ?object => $this->decodeToken($this->extractToken($request))
            )
        );
    }

    /**
     * Extract a token using extractors.
     *
     * @param Request $request
     * @return string|null
     */
    private function extractToken(Request $request): ?string
    {
        $i = 0;
        foreach ($this->extractors as $extractor) {
            $token = $extractor($request, $this->logger);
            if (is_string($token) && $token !== '') {
                return $token;
            }
            $i += 1;
        }

        // log if no extractor found the token
        $this->logger && $this->logger->log(LogLevel::DEBUG, $i > 0 ? 'Token not found.' : 'No extractors.');
        return null;
    }

    /**
     * Decode string token to its payload (claims).
     *
     * If the decoder throws on error, the injector should catch the exceptions.
     *
     * @param string|null $token
     * @return object|null payload
     */
    private function decodeToken(?string $token): ?object
    {
        return $token !== null ? ($this->decoder)($token, $this->logger) : null;
    }

    /**
     * Perform the decoding transaction.
     * The token provider callable contains logic that either returns a decoded token, `null` or throws.
     *
     * @param Request $request
     * @param callable $tokenProvider
     * @return Request
     */
    private function injectRequest(Request $request, callable $tokenProvider): Request
    {
        return ($this->injector)($tokenProvider, $request, $this->logger);
    }
}
