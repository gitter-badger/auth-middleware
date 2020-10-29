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
 * Test of Manipulators::attributeTokenProvider static factory.
 *
 * @see Manipulators::attributeTokenProvider()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _AttributeTokenProviderTest extends TestCase
{
    public function testAttributeTokenProvider()
    {
        // a test token ...
        $token = (object)[
            'sub' => 42,
        ];

        // create a test request with a bunch of attributes
        $request = (new RequestFactory())->createRequest('GET', '/')
            ->withAttribute('token', $token)
            ->withAttribute('', $token)
            ->withAttribute('string', 'type error')
            ->withAttribute('array', ['type error'])
            ->withAttribute('foo', $token);

        Assert::same($token, Manipulators::attributeTokenProvider()($request), 'Fetch `token` attribute by default.');
        Assert::same($token, Manipulators::attributeTokenProvider('token')($request));
        Assert::same($token, Manipulators::attributeTokenProvider('foo')($request));
        Assert::same($token, Manipulators::attributeTokenProvider('')($request)); // an empty string should still be valid

        Assert::throws(function () use ($request) {
            Manipulators::attributeTokenProvider('string')($request);
        }, TypeError::class);
        Assert::throws(function () use ($request) {
            Manipulators::attributeTokenProvider('array')($request);
        }, TypeError::class);
    }
}

// run the test
(new _AttributeTokenProviderTest)->run();
