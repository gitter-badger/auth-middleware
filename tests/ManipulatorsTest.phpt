<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\TokenManipulators;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

/**
 * Test of the other methods of TokenManipulators class.
 *
 * @see TokenManipulators
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _ManipulatorsTest extends TestCase
{
    public function testHandlerWrapper()
    {
        $check = false;
        $handler = TokenManipulators::callableToHandler(function () use (&$check) {
            $check = true;
            return (new ResponseFactory())->createResponse();
        });
        Assert::type(RequestHandlerInterface::class, $handler);

        Assert::false($check);
        /** @noinspection PhpParamsInspection */
        $handler->handle((new RequestFactory())->createRequest('GET', '/'));
        Assert::true($check);
    }
}

// run the test
(new _ManipulatorsTest)->run();
