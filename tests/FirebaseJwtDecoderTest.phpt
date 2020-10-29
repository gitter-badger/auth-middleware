<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ProxyLogger.php';

use Dakujem\Middleware\FirebaseJwtDecoder;
use Dakujem\Middleware\Test\Support\_ProxyLogger;
use LogicException;
use Psr\Log\LogLevel;
use Tester\Assert;
use Tester\TestCase;
use UnexpectedValueException;

/**
 * Test of FirebaseJwtDecoder class.
 *
 * @see FirebaseJwtDecoder
 *
 * @link https://jwt.io for test tokens
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _FirebaseJwtDecoderTest extends TestCase
{
    private string $key = 'Dakujem za halusky!';

    public function testValidToken()
    {
        $token = implode('.', $this->tokenParts());
        $expected = json_decode('{
          "sub": "1234567890",
          "name": "John Doe",
          "iat": 1516239022
        }');
        Assert::equal($expected, (new FirebaseJwtDecoder($this->key))($token));
        Assert::equal($expected, (new FirebaseJwtDecoder($this->key, ['HS256']))($token));
    }

    public function testMalformedToken()
    {
        Assert::null((new FirebaseJwtDecoder($this->key))('foobar'));
        Assert::null((new FirebaseJwtDecoder($this->key))('foo.bar.qux'));
        Assert::null((new FirebaseJwtDecoder($this->key))(''));
    }

    public function testMalformedSignature()
    {
        [$header, $payload, $signature] = $this->tokenParts();
        $token = implode('.', [$header, $payload, $signature . 'FOO']);
        Assert::null((new FirebaseJwtDecoder($this->key))($token));
    }

    public function testMalformedPayload()
    {
        [$header, $payload, $signature] = $this->tokenParts();
        $token = implode('.', [$header, $payload . 'FOO', $signature]);
        Assert::null((new FirebaseJwtDecoder($this->key))($token));
    }

    public function testMalformedHeader()
    {
        [$header, $payload, $signature] = $this->tokenParts();
        $token = implode('.', [$header . 'FOO', $payload, $signature]);
        Assert::null((new FirebaseJwtDecoder($this->key))($token));
    }

    public function testInvalidKey()
    {
        $token = implode('.', $this->tokenParts());
        Assert::null((new FirebaseJwtDecoder('foobar!'))($token));
    }

    public function testInvalidAlgo()
    {
        $token = implode('.', $this->tokenParts());
        Assert::null((new FirebaseJwtDecoder($this->key, ['ritpalova']))($token));
        Assert::null((new FirebaseJwtDecoder($this->key, ['HS512', 'HS384']))($token));
    }

    /**
     * unix 2550679691 somewhere in 2050
     * unix  310068491 somewhere back in 1970-ties (expired)
     */
    public function testExpNbfIat()
    {
        // the following are correctly encoded tokens, except their EXP, NBF or IAT make them invalid
        $expInPast = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsImV4cCI6MzEwMDY4NDkxfQ.lsgezTfa9j8NxBTj5snCzyC6Gf4CQDLG9ravcZq2jp8';
        $iatInFuture = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsImlhdCI6MjU1MDY3OTY5MX0.S77G3dPHMxk0hm0YGLnDuMGtN2ilUxyXSxwmlhc1iNc';
        $nbfInFuture = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsIm5iZiI6MjU1MDY3OTY5MX0.hIn7FSLbzrJDUBxs1yp7hik3WvuzLj34oMnD7_yOVzE';

        $decoder = (new FirebaseJwtDecoder($this->key));
        Assert::null($decoder($expInPast));
        Assert::null($decoder($iatInFuture));
        Assert::null($decoder($nbfInFuture));
    }

    public function testLogsOnFailure()
    {
        $token = implode('.', $this->tokenParts());

        // no logging on decoded
        Assert::notNull(
            (new FirebaseJwtDecoder($this->key))($token, new _ProxyLogger(function () {
                throw new LogicException('This should never be thrown.');
            }))
        );

        // log on error
        Assert::null(
            (new FirebaseJwtDecoder('invalid'))('bad token', new _ProxyLogger(function (
                $level,
                $message,
                $context
            ) {
                Assert::same(LogLevel::DEBUG, $level);
                Assert::true($message !== '');
                Assert::same('bad token', $context[0] ?? null);
                Assert::type(UnexpectedValueException::class, $context[1] ?? null);
            }))
        );
    }

    private function tokenParts(): array
    {
        // a valid token parts
        return [
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', // header
            'eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ', // payload
            'c9csaTSLNAKS-tCtSumgA4IhTHUCCFcVVEwS2zJvqsU', // signature
        ];
    }
}

// run the test
(new _FirebaseJwtDecoderTest)->run();
