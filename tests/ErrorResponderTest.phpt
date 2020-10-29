<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\Manipulators;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

/**
 * Test of Manipulators::basicErrorResponder static factory.
 *
 * @see Manipulators::basicErrorResponder()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _ErrorResponderTest extends TestCase
{
    public function testBasicErrorResponder()
    {
        $rf = new ResponseFactory();
        // sanity test...
        Assert::type(Response::class, $rf->createResponse());
        Assert::same(200, $rf->createResponse()->getStatusCode(), 'Default status of the response factory should be 200.');

        Assert::type(Response::class, Manipulators::basicErrorResponder($rf)());
        Assert::same(400, (Manipulators::basicErrorResponder($rf)())->getStatusCode(), 'Default status code of the error responder should be 400.');
        Assert::same(401, (Manipulators::basicErrorResponder($rf, 401)())->getStatusCode());
        Assert::same(499, (Manipulators::basicErrorResponder($rf, 499)())->getStatusCode());
        Assert::same(500, (Manipulators::basicErrorResponder($rf, 500)())->getStatusCode());
        Assert::same(599, (Manipulators::basicErrorResponder($rf, 599)())->getStatusCode());
        Assert::same(200, (Manipulators::basicErrorResponder($rf, 200)())->getStatusCode());
        Assert::same(299, (Manipulators::basicErrorResponder($rf, 299)())->getStatusCode());
    }
}

// run the test
(new _ErrorResponderTest)->run();
