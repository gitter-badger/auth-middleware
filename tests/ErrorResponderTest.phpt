<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\TokenManipulators;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

/**
 * Test of TokenManipulators::basicErrorResponder static factory.
 *
 * @see TokenManipulators::basicErrorResponder()
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

        Assert::type(Response::class, TokenManipulators::basicErrorResponder($rf)());
        Assert::same(400, (TokenManipulators::basicErrorResponder($rf)())->getStatusCode(), 'Default status code of the error responder should be 400.');
        Assert::same(401, (TokenManipulators::basicErrorResponder($rf, 401)())->getStatusCode());
        Assert::same(499, (TokenManipulators::basicErrorResponder($rf, 499)())->getStatusCode());
        Assert::same(500, (TokenManipulators::basicErrorResponder($rf, 500)())->getStatusCode());
        Assert::same(599, (TokenManipulators::basicErrorResponder($rf, 599)())->getStatusCode());
        Assert::same(200, (TokenManipulators::basicErrorResponder($rf, 200)())->getStatusCode());
        Assert::same(299, (TokenManipulators::basicErrorResponder($rf, 299)())->getStatusCode());
    }
}

// run the test
(new _ErrorResponderTest)->run();
