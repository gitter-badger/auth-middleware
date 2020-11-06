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
 * Test of a pair of factories for callables that read data from Request attributes.
 *
 * @see TokenManipulators::attributeTokenProvider()
 * @see TokenManipulators::attributeExtractor()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _AttributeReadersTest extends TestCase
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

        Assert::same($token, TokenManipulators::attributeTokenProvider()($request), 'Fetch `token` attribute by default.');
        Assert::same($token, TokenManipulators::attributeTokenProvider('token')($request));
        Assert::same($token, TokenManipulators::attributeTokenProvider('foo')($request));
        Assert::same($token, TokenManipulators::attributeTokenProvider('')($request)); // an empty string should still be valid

        Assert::null(TokenManipulators::attributeTokenProvider('other')($request));

        Assert::throws(function () use ($request) {
            TokenManipulators::attributeTokenProvider('string')($request);
        }, TypeError::class);
        Assert::throws(function () use ($request) {
            TokenManipulators::attributeTokenProvider('array')($request);
        }, TypeError::class);
    }

    public function testAttributeExtractor()
    {
        $token = 'any string';

        // create a test request with a bunch of attributes
        $request = (new RequestFactory())->createRequest('GET', '/')
            ->withAttribute('token', $token)
            ->withAttribute('foo', $token)
            ->withAttribute('', $token)
            ->withAttribute('array', ['type error'])
            ->withAttribute('object', (object)['type error']);

        Assert::same($token, TokenManipulators::attributeExtractor()($request), 'Fetch from `token` attribute by default.');
        Assert::same($token, TokenManipulators::attributeExtractor('token')($request));
        Assert::same($token, TokenManipulators::attributeExtractor('foo')($request));
        Assert::same($token, TokenManipulators::attributeExtractor('')($request)); // an empty string should still be valid

        Assert::null(TokenManipulators::attributeExtractor('other')($request));

        Assert::throws(function () use ($request) {
            TokenManipulators::attributeExtractor('object')($request);
        }, TypeError::class);
        Assert::throws(function () use ($request) {
            TokenManipulators::attributeExtractor('array')($request);
        }, TypeError::class);
    }
}

// run the test
(new _AttributeReadersTest)->run();
