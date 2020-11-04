<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use DomainException;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use UnexpectedValueException;

/**
 * A callable decoder that uses Firebase JWT implementation.
 *
 * Note: firebase/php-jwt is a peer dependency, you need to install it separately:
 *   `composer require firebase/php-jwt:"^5.0"`
 *
 * Usage with TokenMiddleware:
 *   $mw = new TokenMiddleware(new FirebaseJwtDecoder('my-secret-is-not-committed-to-the-repo'));
 *
 * Warning:
 *   This decoder _only_ ensures that the token has been signed by the given secret key
 *   and that it is not expired (`exp` claim) or used before intended (`nbf` and `iat` claims).
 *   The rest of the authorization process is entirely up to your app's logic.
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
        if ($secret === '') {
            throw new InvalidArgumentException('The secret key may not be empty.');
        }
        $this->secret = $secret;
        $this->algos = $algos ?? ['HS256', 'HS512', 'HS384'];
    }

    /**
     * Decodes a raw token.
     * Respects these 3 registered claims: `exp` (expiration), `nbf`/`iat` (not before).
     *
     * @param string $token raw token string
     * @param LoggerInterface|null $logger
     * @return object|null decoded token payload
     * @throws UnexpectedValueException
     */
    public function __invoke(string $token, ?LoggerInterface $logger = null): object
    {
        try {
            return JWT::decode(
                $token,
                $this->secret,
                $this->algos
            );
        } catch (UnexpectedValueException $throwable) {
            $logger && $logger->log(LogLevel::DEBUG, $throwable->getMessage(), [$token, $throwable]);
            throw $throwable;
        } catch (DomainException $throwable) {
            $re = new UnexpectedValueException(
                'The JWT is malformed, invalid JSON.',
                $throwable->getCode(),
                $throwable
            );
            $logger && $logger->log(LogLevel::DEBUG, $re->getMessage(), [$token, $throwable]);
            throw $re;
        }
    }
}
