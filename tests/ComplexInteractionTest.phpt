<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ProxyLogger.php';

use Dakujem\Middleware\Factory\AuthWizard;
use Dakujem\Middleware\TokenManipulators;
use Firebase\JWT\JWT;
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
            return (new ResponseFactory())->createResponse();
        });
        $dispatcher = new MiddlewareDispatcher($kernel);

        // add the middleware
        foreach ($middleware as $mw) {
            $dispatcher->add($mw);
        }

        // dispatch the request
        $response = $dispatcher->handle($request);

        // check the response
        $checkResponse && $checkResponse($response);
    }


    // TODO refactor is overdue...

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    private function req(): Request
    {
        return (new RequestFactory())->createRequest('GET', '/');
    }

    // TODO refactor is overdue...
    private function validToken(): string
    {
        return JWT::encode([
            'sub' => 42,
            'foo' => 'bar',
        ], $this->key);
    }

    // TODO refactor is overdue...
    private string $key = 'Dakujem za halusky!';

    public function testHappyPath()
    {
        $mw = [
            AuthWizard::decodeTokens($this->key),
        ];
        $request = $this->req()->withHeader('Authorization', 'Bearer ' . $this->validToken());
        self::check($mw, $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
            Assert::same(42, $request->getAttribute('token')->sub);
        });
        $request = $this->req()->withCookieParams(['token' => $this->validToken()]);
        self::check($mw, $request, function (Request $request) {
            Assert::notNull($request->getAttribute('token'));
            Assert::same(42, $request->getAttribute('token')->sub);
        });
    }

}

// run the test
(new _ComplexInteractionTest)->run();
