<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\TokenManipulators;
use Dakujem\Middleware\TokenManipulators as Man;
use LogicException;
use RuntimeException;
use Slim\Psr7\Factory\RequestFactory;
use Tester\Assert;
use Tester\TestCase;
use TypeError;

/**
 * Test of TokenManipulators::attributeInjector static factory.
 *
 * @see TokenManipulators::attributeInjector()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _AttributeInjectorTest extends TestCase
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
            (Man::attributeInjector()(fn() => $token, $request))->getAttribute('token'),
            'The token should be written to the \'token\' attribute by default.'
        );
        Assert::same($token, (Man::attributeInjector('foo')(fn() => $token, $request))->getAttribute('foo'));
        Assert::same($token, (Man::attributeInjector('')(fn() => $token, $request))->getAttribute(''));

        $errm = 'This will appear in the error message.';
        $runtime = function () use ($errm) {
            throw new RuntimeException($errm);
        };
        $logic = function () use ($errm) {
            throw new LogicException($errm);
        };
        Assert::same(
            "Token error: {$errm}",
            (Man::attributeInjector()($runtime, $request))->getAttribute('token.error'),
            'The error message should be written to the \'token.error\' attribute by default.'
        );
        Assert::same(
            null,
            (Man::attributeInjector()($runtime, $request))->getAttribute('token'),
            'Nothing should be written to the \'token\' attribute.'
        );
        Assert::same(
            "Token error: {$errm}",
            (Man::attributeInjector('whatever', 'foo.bar')($runtime, $request))->getAttribute('foo.bar')
        );
        Assert::same(
            "Token error: {$errm}",
            (Man::attributeInjector('whatever', '')($runtime, $request))->getAttribute('')
        );

        // The injector only traps `RuntimeException` errors, other errors will be unhandled:
        Assert::throws(fn() => Man::attributeInjector()($logic, $request), LogicException::class, $errm);

        // On null token (token is not found in the request by the extractors), nothing is written
        $resp = (Man::attributeInjector()(fn() => null, $request));
        Assert::null($resp->getAttribute('token'));
        Assert::null($resp->getAttribute('token.error'));

        // Type error, the first argument must be callable
        Assert::throws(function () use ($request, $token) {
            Man::attributeInjector()($token, $request);
        }, TypeError::class);
        Assert::throws(function () use ($request) {
            Man::attributeInjector()(42, $request);
        }, TypeError::class);
    }
}

// run the test
(new _AttributeInjectorTest)->run();
