<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\TokenManipulators;
use Slim\Psr7\Factory\RequestFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of TokenManipulators::attributeTokenProvider static factory.
 *
 * @see TokenManipulators::attributeExtractor()
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

        Assert::same($token, TokenManipulators::attributeExtractor()($request), 'Fetch `token` attribute by default.');
        Assert::same($token, TokenManipulators::attributeExtractor('token')($request));
        Assert::same($token, TokenManipulators::attributeExtractor('foo')($request));
        Assert::same($token, TokenManipulators::attributeExtractor('')($request)); // an empty string should still be valid

        Assert::throws(function () use ($request) {
            TokenManipulators::attributeExtractor('string')($request);
        }, TypeError::class);
        Assert::throws(function () use ($request) {
            TokenManipulators::attributeExtractor('array')($request);
        }, TypeError::class);
    }
}

// run the test
(new _AttributeTokenProviderTest)->run();
