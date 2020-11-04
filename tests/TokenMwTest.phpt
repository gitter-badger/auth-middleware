<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ProxyLogger.php';

use ArrayIterator;
use Dakujem\Middleware\FirebaseJwtDecoder;
use Dakujem\Middleware\Test\Support\_ProxyLogger;
use Dakujem\Middleware\TokenManipulators;
use Dakujem\Middleware\TokenMiddleware;
use Firebase\JWT\JWT;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of TokenMiddleware class.
 *
 * @see TokenMiddleware
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _TokenMwTest extends TestCase
{
    private string $key = 'Dakujem za halusky!';

//    protected function setUp()
//    {
//        parent::setUp();
//    }
//
//    protected function tearDown()
//    {
//        parent::tearDown();
//    }

    private function validToken(): string
    {
        return JWT::encode([
            'sub' => 42,
            'foo' => 'bar',
        ], $this->key);
    }

    public function testHappyPath()
    {
        $default = (new RequestFactory())->createRequest('GET', '/');
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                $token = $request->getAttribute('token', null);
                Assert::type('object', $token);
                Assert::same(42, $token->sub);
                Assert::same('bar', $token->foo);

                return (new ResponseFactory)->createResponse(418); // I'm a teapot.
            }
        };

        $mw = new TokenMiddleware(new FirebaseJwtDecoder($this->key));

        /** @noinspection PhpParamsInspection */
        $response = $mw->process($default->withHeader('Authorization', 'Bearer ' . $this->validToken()), $next);
        Assert::same(418, $response->getStatusCode());

        $response = $mw->process($default->withCookieParams(['token' => $this->validToken()]), $next);
        Assert::same(418, $response->getStatusCode());
    }

    public function testNoToken()
    {
        $default = (new RequestFactory())->createRequest('GET', '/');
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                Assert::null($request->getAttribute('token', null));

                return (new ResponseFactory)->createResponse(418); // I'm a teapot.
            }
        };

        $logged = false;
        $mw = new TokenMiddleware(
            new FirebaseJwtDecoder($this->key),
            null,
            null,
            new _ProxyLogger(function ($level, $msg, $ctx) use (&$logged) {
                $logged = true;
                Assert::same(LogLevel::DEBUG, $level);
                Assert::same('Token not found.', $msg);
                Assert::same([], $ctx);
            })
        );

        /** @noinspection PhpParamsInspection */
        $response = $mw->process($default, $next);
        Assert::same(418, $response->getStatusCode());
        Assert::true($logged, 'The logger has not been called.');
    }

    /** @noinspection PhpUndefinedFieldInspection */
    public function testIntrospect1()
    {
        $mw = new TokenMiddleware($decoder = fn() => null);
        Assert::with($mw, function () use ($decoder) {
            // test private methods
            Assert::same($decoder, $this->decoder);
            Assert::count(2, iterator_to_array($this->extractors));
            Assert::notNull($this->injector);
            Assert::null($this->logger);
        });
    }

    /** @noinspection PhpUndefinedFieldInspection */
    public function testIntrospect2()
    {
        $mw = new TokenMiddleware(
            $decoder = fn() => null,
            $extractors = new ArrayIterator([]),
            $injector = TokenManipulators::attributeInjector(),
            $logger = new _ProxyLogger(fn() => null)
        );
        Assert::with($mw, function () use ($decoder, $extractors, $injector, $logger) {
            // test private methods
            Assert::same($decoder, $this->decoder);
            Assert::same($extractors, $this->extractors);
            Assert::same($injector, $this->injector);
            Assert::same($logger, $this->logger);
        });
    }

    public function testUsesAllExtractors()
    {
        // Check that ALL the extractors are called for any acceptable type:
        // 1/ arrays
        $this->checkAllExtractors(fn(array $ext) => $ext);
        // 2/ iterators
        $this->checkAllExtractors(fn(array $ext) => new ArrayIterator($ext));
        // 3/ generators
        $this->checkAllExtractors(fn(array $ext) => (fn() => yield from $ext)());
    }

    private function checkAllExtractors(callable $makeExtractors)
    {
        $e1 = false;
        $e2 = false;
        $e3 = false;
        $unwrapped = [
            function () use (&$e1) {
                $e1 = true;
                return null;
            },
            function () use (&$e2) {
                $e2 = true;
                return null;
            },
            function () use (&$e3) {
                $e3 = true;
                return null;
            },
        ];
        $extractors = $makeExtractors($unwrapped);

        $check = Assert::with(
            new TokenMiddleware(
                fn() => null,
                $extractors
            ),
            function () {
                $request = (new RequestFactory())->createRequest('GET', '/');
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::null($token = $this->extractToken($request));
                return $token;
            }
        );

        Assert::true($e1);
        Assert::true($e2);
        Assert::true($e3);
        Assert::null($check);
    }

    public function testUsesCorrectExtractors()
    {
        // Check that ALL the extractors are called for any acceptable type:
        // 1/ arrays
        $this->checkExtractionStopsWhenFound(fn(array $ext) => $ext);
        // 2/ iterators
        $this->checkExtractionStopsWhenFound(fn(array $ext) => new ArrayIterator($ext));
        // 3/ generators
        $this->checkExtractionStopsWhenFound(fn(array $ext) => (fn() => yield from $ext)());
    }

    private function checkExtractionStopsWhenFound(callable $makeExtractors)
    {
        $e1 = false;
        $e2 = false;
        $unwrapped = [
            function () use (&$e1) {
                $e1 = true;
                return null;
            },
            function () use (&$e2) {
                $e2 = true;
                return 'token42'; // should extract this token
            },
            function () {
                throw new LogicException('This must not be executed.');
            },
        ];
        $extractors = $makeExtractors($unwrapped);

        $check = Assert::with(
            new TokenMiddleware(
                fn() => null,
                $extractors
            ),
            function () {
                $request = (new RequestFactory())->createRequest('GET', '/');
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::same('token42', $token = $this->extractToken($request));
                return $token;
            }
        );

        Assert::true($e1);
        Assert::true($e2);
        Assert::notNull($check);
    }

    public function testNoExtractorsFindNothing()
    {
        $request = $this->req();
        $check = Assert::with(
            new TokenMiddleware(
                fn() => null,
                []
            ),
            function () use ($request) {
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::null($token = $this->extractToken($request));
                return $token;
            }
        );
        Assert::null($check);
    }

    public function testLogWhenNoTokenFound()
    {
        $logged = false;
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [],
                null,
                new _ProxyLogger(function ($level, $msg, $ctx) use (&$logged) {
                    Assert::same(LogLevel::DEBUG, $level);
                    Assert::same('Token not found.', $msg);
                    Assert::same([], $ctx);
                    $logged = true;
                })
            ),
            function () {
                $request = (new RequestFactory())->createRequest('GET', '/');
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::null($this->extractToken($request));
            }
        );
        Assert::true($logged);
    }

    public function testDoNotLogWhenTokenFound()
    {
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [fn() => 'token42'],
                null,
                new _ProxyLogger(function () {
                    throw new LogicException('Logger should not be used when a token is found.');
                })
            ),
            function () {
                $request = (new RequestFactory())->createRequest('GET', '/');
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::same('token42', $this->extractToken($request));
            }
        );
    }

    public function testEveryExtractorReceivesCorrectArguments()
    {
        $request = $this->req();
        $logger = new _ProxyLogger(fn() => null);
        $extractors = [
            function (Request $req, LoggerInterface $log) use ($request, $logger) {
                Assert::same($request, $req);
                Assert::same($logger, $log);
                return null;
            },
            function (Request $req, LoggerInterface $log) use ($request, $logger) {
                Assert::same($request, $req);
                Assert::same($logger, $log);
                return null;
            },
        ];

        $check = Assert::with(
            new TokenMiddleware(
                fn() => null,
                $extractors,
                null,
                $logger
            ),
            function () use ($request) {
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                $this->extractToken($request);
                return true;
            }
        );
        Assert::true($check);
    }

    public function testDecoderReceivesCorrectArguments()
    {
        $logger = new _ProxyLogger(fn() => null);
        $check = Assert::with(
            new TokenMiddleware(
                function (string $token, LoggerInterface $log) use ($logger) {
                    Assert::same($logger, $log);
                    Assert::same('token42', $token);
                },
                [],
                null,
                $logger
            ),
            function () {
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                $this->decodeToken('token42');
                return true;
            }
        );
        Assert::true($check);
    }

    public function testDecoderNotCalledWhenExtractedTokenIsNull()
    {
        $check = Assert::with(
            new TokenMiddleware(
                function () {
                    throw new LogicException('This should never be thrown, decoder should not be invoked.');
                },
                [],
            ),
            function () {
                /** @noinspection PhpUndefinedMethodInspection */
                // Note: `$this` is the instance of TokenMiddleware created above ^
                Assert::null($this->decodeToken(null));
                return true;
            }
        );
        Assert::true($check);
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function testDecoderCanOnlyReturnObjectsOrNull()
    {
        Assert::with(
            new TokenMiddleware(
                fn() => null, // null is OK
                [],
            ),
            function () {
                Assert::null($this->decodeToken('does not matter'));
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => (object)[], // an object is okay
                [],
            ),
            function () {
                Assert::notNull($this->decodeToken('does not matter'));
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => new TokenManipulators(), // any class instance
                [],
            ),
            function () {
                Assert::type(TokenManipulators::class, $this->decodeToken('does not matter'));
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => [],
                [],
            ),
            function () {
                Assert::throws(fn() => $this->decodeToken('does not matter'), TypeError::class);
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => 'invalid return type',
                [],
            ),
            function () {
                Assert::throws(fn() => $this->decodeToken('does not matter'), TypeError::class);
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => 42,
                [],
            ),
            function () {
                Assert::throws(fn() => $this->decodeToken('does not matter'), TypeError::class);
            }
        );
    }

    public function testInjectorReceivesCorrectArguments()
    {
        $provider = fn() => null;
        $request = $this->req();
        $logger = new _ProxyLogger(fn() => null);
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [],
                function (callable $prov, Request $req, LoggerInterface $log) use ($provider, $request, $logger) {
                    Assert::same($provider, $prov);
                    Assert::same($request, $req);
                    Assert::same($logger, $log);
                    return $req;
                },
                $logger
            ),
            function () use ($request, $provider) {
                /** @noinspection PhpUndefinedMethodInspection */
                $rv = $this->injectRequest($request, $provider);
                Assert::same($request, $rv);
            }
        );
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function testInjectorCanOnlyReturnARequest()
    {
        $request = $this->req();
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [],
                fn() => $request, // OK
            ),
            function () use ($request) {
                Assert::type(Request::class, $this->injectRequest($request, fn() => 'does not matter'));
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [],
                fn() => new TokenManipulators(), // NOT OK
            ),
            function () use ($request) {
                Assert::throws(fn() => $this->injectRequest($request, fn() => 'does not matter'), TypeError::class);
            }
        );
        Assert::with(
            new TokenMiddleware(
                fn() => null,
                [],
                fn() => null, // NOT OK
            ),
            function () use ($request) {
                Assert::throws(fn() => $this->injectRequest($request, fn() => 'does not matter'), TypeError::class);
            }
        );
    }

    public function testInjectorReceivesCorrectlyComposedProvider()
    {
        $mw = new TokenMiddleware(
        // 2/ the decoder creates an object containing the raw token for check
            fn(string $rawToken) => (object)['ok' => 42, 'raw' => $rawToken],
            // 1/ the extractors will "find" a token
            [fn() => 'raw token found'],
            // 3/ the injector writes the token obtained through the provider into the request
            fn(callable $provider, Request $req): Request => $req->withAttribute('token', $provider()),
        );
        $response = $mw->process(
            $this->req(),
            TokenManipulators::callableToHandler(function (Request $req) {
                $token = $req->getAttribute('token');
                Assert::notNull($token);
                Assert::same('raw token found', $token->raw);
                Assert::same(42, $token->ok);
                return (new ResponseFactory())->createResponse(418);
            })
        );
        Assert::same(418, $response->getStatusCode());
    }

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    private function req(): Request
    {
        return (new RequestFactory())->createRequest('GET', '/');
    }
}

// run the test
(new _TokenMwTest)->run();
