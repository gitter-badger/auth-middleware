<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * A decoder that uses Firebase JWT implementation.
 *
 * Note: firebase/php-jwt is a peer dependency, you need to install it separately:
 *   `composer require firebase/php-jwt:"^5.0"`
 *
 * Usage with TokenMiddleware:
 *   $mw = new TokenMiddleware(new FirebaseJwtDecoder('my-secret-is-not-committed-to-the-repo'));
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class FirebaseJwtDecoder
{
    private string $secret;
    /** @var string[] */
    private array $algos;

    public function __construct(string $secret, ?array $algos = null)
    {
        $this->secret = $secret;
        $this->algos = $algos ?? ['HS256', 'HS512', 'HS384'];
    }

    /**
     * Decodes a raw token.
     * Respects these 3 registered claims: `exp` (expiration), `nbf`/`iat` (not before).
     *
     * Does not throw exceptions. Any throwable is intercepted and `null` is returned.
     *
     * @param string|null $token raw token string
     * @param LoggerInterface|null $logger
     * @return object|null decoded token payload
     */
    public function __invoke(?string $token, ?LoggerInterface $logger): ?object
    {
        try {
            return JWT::decode(
                $token,
                $this->secret,
                $this->algos
            );
        } catch (Throwable $throwable) {
            $logger && $logger->log(LogLevel::INFO, $throwable->getMessage(), [$token, $throwable]);
        }
        return null;
    }
}
