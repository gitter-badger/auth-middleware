<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\Factory\AuthWizard;
use Dakujem\Middleware\GenericHandler;
use Dakujem\Middleware\GenericMiddleware;
use Dakujem\Middleware\TokenManipulators;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of GenericMiddleware class and associated helper.
 *
 * @see GenericMiddleware
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _GenericMiddlewareTest extends TestCase
{
    public function testInterface()
    {
        $check = false;
        $testee = new GenericMiddleware(function ($request, $next) use (&$check) {
            $check = true;

            // assert correct arguments
            Assert::type(ServerRequestInterface::class, $request);
            Assert::type(RequestHandlerInterface::class, $next);

            return $next->handle($request);
        });
        Assert::type(MiddlewareInterface::class, $testee);

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee->process(
            (new RequestFactory())->createRequest('GET', '/'),
            new GenericHandler(fn() => (new ResponseFactory())->createResponse())
        );
        Assert::true($check);
    }

    public function testDirectCall()
    {
        $check = false;
        $testee = new GenericMiddleware(function ($request, $next) use (&$check) {
            $check = true;

            // assert correct arguments
            Assert::type(ServerRequestInterface::class, $request);
            Assert::type(RequestHandlerInterface::class, $next);

            return $next->handle($request);
        });

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee(
            (new RequestFactory())->createRequest('GET', '/'),
            new GenericHandler(fn() => (new ResponseFactory())->createResponse())
        );
        Assert::true($check);

        $check = false;
        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee(
            (new RequestFactory())->createRequest('GET', '/'),
            fn() => (new ResponseFactory())->createResponse() // assert the handler is created on the fly
        );
        Assert::true($check);
    }

    public function testWrapper()
    {
        $check = false;
        $testee = TokenManipulators::callableToMiddleware(function ($request, $next) use (&$check) {
            $check = true;
            return $next->handle($request);
        });
        Assert::type(GenericMiddleware::class, $testee);
        Assert::type(MiddlewareInterface::class, $testee);

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee->process(
            (new RequestFactory())->createRequest('GET', '/'),
            new GenericHandler(fn() => (new ResponseFactory())->createResponse())
        );
        Assert::true($check);
    }

    /** @noinspection PhpParamsInspection */
    public function testMiddlewareMustReturnAResponse()
    {
        $req = (new RequestFactory())->createRequest('GET', '/');
        $next = new GenericHandler(fn() => (new ResponseFactory())->createResponse());
        Assert::throws(fn() => (new GenericMiddleware(fn() => null))->process($req, $next), TypeError::class);
        Assert::throws(fn() => (new GenericMiddleware(fn() => 42))->process($req, $next), TypeError::class);
        Assert::throws(fn() => (new GenericMiddleware(fn() => 'hello world'))->process($req, $next), TypeError::class);
        Assert::throws(fn() => (new GenericMiddleware(fn() => new AuthWizard()))
            ->process($req, $next), TypeError::class);
    }
}

// run the test
(new _GenericMiddlewareTest)->run();
