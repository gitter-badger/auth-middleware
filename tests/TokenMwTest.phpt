<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\PredicateMiddleware;
use Dakujem\Middleware\TokenMiddleware;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
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
    protected function setUp()
    {
        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testFoo(){
        Assert::same(1, 1);
    }
}

// run the test
(new _TokenMwTest)->run();
