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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LogLevel;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

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
}

// run the test
(new _TokenMwTest)->run();
