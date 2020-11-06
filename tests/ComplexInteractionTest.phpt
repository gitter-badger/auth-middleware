<?php
/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ProxyLogger.php';

use Dakujem\Middleware\Factory\AuthWizard;
use Dakujem\Middleware\TokenManipulators;
use Firebase\JWT\JWT;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\MiddlewareDispatcher;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;


/**
 * Complex test suite for the middleware interaction.
 *
 * These test check the interaction of the MW with both the Request and the Response.
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _ComplexInteractionTest extends TestCase
{
    private static function check(
        iterable $middleware,
        Request $request,
        ?callable $checkRequest,
        ?callable $checkResponse = null
    ): void {
        // create a new dispatcher with a kernel
        $kernel = TokenManipulators::callableToHandler(function (Request $request) use ($checkRequest): Response {
            // check the request at this point
            $checkRequest && $checkRequest($request);

            // return a new 200 response
            return (new ResponseFactory())->createResponse();//->withBody((new StreamFactory())->createStream());
        });
        $dispatcher = new MiddlewareDispatcher($kernel);

        // add the middleware
        foreach ($middleware as $mw) {
            $dispatcher->add($mw);
        }

        // dispatch the request
        $response = $dispatcher->handle($request);

        // check the response
        $response->getBody()->rewind();
        $checkResponse && $checkResponse($response);
    }


    // TODO refactor is due...

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    private function req(): Request
    {
        return (new RequestFactory())->createRequest('GET', '/');
    }

    // TODO refactor is due...
    private function validToken(): string
    {
        return JWT::encode([
            'sub' => 42,
            'foo' => 'bar',
        ], $this->key);
    }

    // TODO refactor is due...
    private string $key = 'Dakujem za halusky!';

    public function testTokenIsPresent()
    {
        $mw = fn() => [
            AuthWizard::decodeTokens($this->key),
        ];
        $request = $this->req()->withCookieParams(['token' => $this->validToken()]);
        self::check($mw(), $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
            Assert::same(42, $request->getAttribute('token')->sub);
        });
        $request = $this->req()->withHeader('Authorization', 'Bearer ' . $this->validToken());
        self::check($mw(), $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
            Assert::same(42, $request->getAttribute('token')->sub);
        });
    }

    public function testTokenIsNotPresent()
    {
        $mw = fn() => [
            AuthWizard::decodeTokens($this->key),
        ];
        $request = $this->req();
        self::check($mw(), $request, function (Request $request) {
            Assert::null($request->getAttribute('token'));        // no token, but
            Assert::null($request->getAttribute('token.error'));  // no error either
        });
        self::check($mw(), $request, function (Request $request) {
            Assert::null($request->getAttribute('token'));        // no token, but
            Assert::null($request->getAttribute('token.error'));  // no error either
        });
    }

    public function testInvalidTokenIsPresent()
    {
        $mw = fn() => [
            AuthWizard::decodeTokens($this->key),
        ];
        $request = $this->req()->withCookieParams(['token' => 'malformed.token']);
        self::check($mw(), $request, function (Request $request) {
            Assert::null($request->getAttribute('token'));        // no token and an error is present
            Assert::same('Token error: Wrong number of segments', $request->getAttribute('token.error'));
        });
        $request = $this->req()->withHeader('Authorization', 'Bearer malformed.token');
        self::check($mw(), $request, function (Request $request) {
            Assert::null($request->getAttribute('token'));        // no token and an error is present
            Assert::same('Token error: Wrong number of segments', $request->getAttribute('token.error'));
        });
    }

    public function testTokenAssertion()
    {
        $mw = fn() => [
            AuthWizard::assertTokens(new ResponseFactory()),
            AuthWizard::decodeTokens($this->key),
        ];

        // a valid token present
        $request = $this->req()->withHeader('Authorization', 'Bearer ' . $this->validToken());
        self::check($mw(), $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
        }, function (Response $response) {
            Assert::same(200, $response->getStatusCode());
        });

        // no or invalid token present
        $responseCheck = function (Response $response) {
            Assert::same(401, $response->getStatusCode());
        };
        $requestCheck = function () {
            throw new LogicException('The following middleware should never be reached.');
        };
        $request = $this->req(); // no token
        self::check($mw(), $request, $requestCheck, $responseCheck);
        $request = $this->req()->withHeader('Authorization', 'Bearer malformed.token'); // invalid token
        self::check($mw(), $request, $requestCheck, $responseCheck);
    }

    public function testWithErrorPass()
    {
        $mw = fn() => [
            AuthWizard::assertTokens(new ResponseFactory(), 'token', 'token.error'),
            AuthWizard::decodeTokens($this->key, 'token', 'Authorization', 'token', 'token.error'),
        ];

        // a valid token present
        $request = $this->req()->withHeader('Authorization', 'Bearer ' . $this->validToken());
        self::check($mw(), $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
        }, function (Response $response) {
            Assert::same(200, $response->getStatusCode());
        });

        // no or invalid token present
        $requestCheck = function () {
            throw new LogicException('The following middleware should never be reached.');
        };
        $request = $this->req(); // no token
        self::check($mw(), $request, $requestCheck, function (Response $response) {
            Assert::same(401, $response->getStatusCode());
            Assert::same(json_encode(['error' => ['message' => 'No valid token found.']]), $response->getBody()->getContents());
        });
        $request = $this->req()->withHeader('Authorization', 'Bearer malformed.token'); // invalid token
        self::check($mw(), $request, $requestCheck, function (Response $response) {
            Assert::same(401, $response->getStatusCode());
            Assert::same(json_encode(['error' => ['message' => 'Token error: Wrong number of segments']]), $response->getBody()->getContents());
        });
    }

    public function testRejectByInspector()
    {
        $mw = fn() => [
            AuthWizard::inspectTokens(
                new ResponseFactory(),
                function (object $token, callable $next, callable $withError): Response {
                    Assert::notNull($token);
                    Assert::same(42, $token->sub);
                    return $withError('I don\'t like the color of your token!'); // reject the token
                },
                'my-token',
                'my-token-error'
            ),
            AuthWizard::decodeTokens($this->key, 'my-token', 'Here-Is-My-Token', null, 'my-token-error'),
        ];

        // the token is indeed valid, but will be rejected by the inspector
        $request = $this->req()->withHeader('here-is-my-token', 'Bearer ' . $this->validToken());
        self::check($mw(), $request, function (Request $request) {
            throw new LogicException('The following middleware should never be reached.');
        }, function (Response $response) {
            Assert::same(401, $response->getStatusCode());
            Assert::same(json_encode(['error' => ['message' => 'I don\'t like the color of your token!']]), $response->getBody()->getContents());
        });
    }

    public function testAcceptByInspector()
    {
        $mw = fn() => [
            AuthWizard::inspectTokens(
                new ResponseFactory(),
                function (object $token, callable $next, callable $withError): Response {
                    Assert::notNull($token);
                    if ($token->sub === 42) {
                        return $next();
                    }
                    return $withError('This should not happen!');
                },
                'my-token',
                'my-token-error'
            ),
            AuthWizard::decodeTokens($this->key, 'my-token', 'Here-Is-My-Token', null, 'my-token-error'),
        ];

        // the token is indeed valid, but will be rejected by the inspector
        $request = $this->req()->withHeader('here-is-my-token', 'Bearer ' . $this->validToken());
        $check = false;
        self::check($mw(), $request, function (Request $request) use (&$check) {
            $check = true;
        }, function (Response $response) {
            Assert::same(200, $response->getStatusCode());
            Assert::same('', $response->getBody()->getContents());
        });
        Assert::true($check, 'The kernel middleware must be reached.');
    }

    public function testMultipleDecodingMiddleware()
    {
        $mw = fn() => [
            AuthWizard::decodeTokens($this->key, 'my-token', 'Here-Is-My-Token', null, 'my-token-error'),
            AuthWizard::decodeTokens($this->key, 'foobar', 'Foobar', 'foobar', 'foobar.error'),
            AuthWizard::decodeTokens($this->key),
        ];

        $request = $this->req()
            ->withHeader('Authorization', 'Bearer ' . $this->validToken())
            ->withHeader('here-is-my-token', 'Bearer ' . $this->validToken());
        $check = false;
        self::check($mw(), $request, function (Request $request) use (&$check) {
            $check = true;
            Assert::notNull($request->getAttribute('token'));
            Assert::notNull($request->getAttribute('my-token'));
            Assert::null($request->getAttribute('foobar'));
            Assert::same(42, $request->getAttribute('token')->sub);
            Assert::same(42, $request->getAttribute('my-token')->sub);
            Assert::true($request->getAttribute('token') !== $request->getAttribute('my-token'), 'The tokens must be two separate instances!');
        }, function (Response $response) {
            Assert::same(200, $response->getStatusCode());
            Assert::same('', $response->getBody()->getContents());
        });
        Assert::true($check, 'The kernel middleware must be reached.');
    }
}

// run the test
(new _ComplexInteractionTest)->run();
