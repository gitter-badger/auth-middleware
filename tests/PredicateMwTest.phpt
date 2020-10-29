<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\Manipulators;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

/**
 * Test of PredicateMiddleware class.
 *
 * @see PredicateMiddleware
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _PredicateMwTest extends TestCase
{
    public function testExecutionCorrectlyPassedToNextHandler()
    {
        $passingPredicate = fn() => true;
        $failingPredicate = fn() => false;
        $errorResponder = Manipulators::callableToHandler(function () {
            throw new LogicException('This should never be called.');
        });
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return (new ResponseFactory)->createResponse(204); // No content for you!
            }
        };
        $request = (new RequestFactory())->createRequest('GET', '/');

        $mw = new PredicateMiddleware($passingPredicate, $errorResponder);
        /** @noinspection PhpParamsInspection */
        $response = $mw->process($request, $next);

        // Check if the handler returns 204.
        Assert::same(204, $response->getStatusCode(), 'The status code of the response should be 204.');

        // sanity test - if a failing predicate is passed, the error handler is called and throws
        $mw = new PredicateMiddleware($failingPredicate, $errorResponder);
        Assert::throws(function () use ($mw, $request, $next) {
            /** @noinspection PhpParamsInspection */
            $mw->process($request, $next);
        }, LogicException::class, 'This should never be called.');
    }

    public function testErrorResponderIsCalledOnPredicateFail()
    {
        $failingPredicate = fn() => false;
        $passingPredicate = fn() => true;
        $errorResponder = Manipulators::callableToHandler(
            fn() => (new ResponseFactory)->createResponse(418) // I'm a teapot
        );
        $next = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                throw new LogicException('This should never be called.');
            }
        };
        $request = (new RequestFactory())->createRequest('GET', '/');

        $mw = new PredicateMiddleware($failingPredicate, $errorResponder);
        /** @noinspection PhpParamsInspection */
        $response = $mw->process($request, $next);

        // The request has indeed been served by a teapot...
        Assert::same(418, $response->getStatusCode());

        // sanity test - if a passing predicate is passed, the next handler is indeed called and throws
        $mw = new PredicateMiddleware($passingPredicate, $errorResponder);
        Assert::throws(function () use ($mw, $request, $next) {
            /** @noinspection PhpParamsInspection */
            $mw->process($request, $next);
        }, LogicException::class, 'This should never be called.');
    }
}

// run the test
(new _PredicateMwTest)->run();
