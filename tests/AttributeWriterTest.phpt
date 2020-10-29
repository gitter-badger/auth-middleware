<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\Manipulators;
use Slim\Psr7\Factory\RequestFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of Manipulators::attributeWriter static factory.
 *
 * @see Manipulators::attributeWriter()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _AttributeWriterTest extends TestCase
{
    public function testWriter()
    {
        // a test token ...
        $token = (object)[
            'sub' => 42,
        ];

        // create an empty test request
        $request = (new RequestFactory())->createRequest('GET', '/');

        // sanity test (no token there)
        Assert::same(null, $request->getAttribute('token'));

        Assert::same(
            $token,
            (Manipulators::attributeWriter()($token, $request))->getAttribute('token'),
            'The token is written to the \'token\' attribute by default.'
        );
        Assert::same($token, (Manipulators::attributeWriter('foo')($token, $request))->getAttribute('foo'));
        Assert::same($token, (Manipulators::attributeWriter('')($token, $request))->getAttribute(''));

        Assert::throws(function () use ($request) {
            Manipulators::attributeWriter()(42, $request);
        }, TypeError::class);
    }
}

// run the test
(new _AttributeWriterTest)->run();
