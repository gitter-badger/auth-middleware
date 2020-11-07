<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\Factory\AuthWizard;
use Dakujem\Middleware\GenericHandler;
use Dakujem\Middleware\TokenManipulators;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of GenericHandler class and associated helper.
 *
 * @see GenericHandler
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _GenericHandlerTest extends TestCase
{
    public function testInterface()
    {
        $check = false;
        $testee = new GenericHandler(function ($request) use (&$check) {
            $check = true;

            // assert correct arguments
            Assert::type(ServerRequestInterface::class, $request);

            return (new ResponseFactory())->createResponse();
        });
        Assert::type(RequestHandlerInterface::class, $testee);

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee->handle((new RequestFactory())->createRequest('GET', '/'));
        Assert::true($check);
    }

    public function testHandlerDirectCall()
    {
        $check = false;
        $testee = new GenericHandler(function ($request) use (&$check) {
            $check = true;

            // assert correct arguments
            Assert::type(ServerRequestInterface::class, $request);

            return (new ResponseFactory())->createResponse();
        });

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee((new RequestFactory())->createRequest('GET', '/'));
        Assert::true($check);
    }

    public function testHandlerWrapper()
    {
        $check = false;
        $testee = TokenManipulators::callableToHandler(function () use (&$check) {
            $check = true;
            return (new ResponseFactory())->createResponse();
        });
        Assert::type(GenericHandler::class, $testee);
        Assert::type(RequestHandlerInterface::class, $testee);

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $testee->handle((new RequestFactory())->createRequest('GET', '/'));
        Assert::true($check);
    }

    /** @noinspection PhpParamsInspection */
    public function testHandlerMustReturnAResponse()
    {
        $req = (new RequestFactory())->createRequest('GET', '/');
        Assert::throws(fn() => (new GenericHandler(fn() => null))->handle($req), TypeError::class);
        Assert::throws(fn() => (new GenericHandler(fn() => 42))->handle($req), TypeError::class);
        Assert::throws(fn() => (new GenericHandler(fn() => 'hello world'))->handle($req), TypeError::class);
        Assert::throws(fn() => (new GenericHandler(fn() => new AuthWizard()))->handle($req), TypeError::class);
    }
}

// run the test
(new _GenericHandlerTest)->run();
