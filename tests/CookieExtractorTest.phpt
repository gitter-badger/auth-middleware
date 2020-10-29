<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ExtractorTestCase.php';

use Dakujem\Middleware\Manipulators;
use Dakujem\Middleware\Test\Support\_ExtractorTestCase;
use LogicException;
use Psr\Log\LogLevel;
use Slim\Psr7\Factory\RequestFactory;
use Tester\Assert;

/**
 * Test of the Manipulators::cookieExtractor static factory.
 *
 * @see Manipulators::cookieExtractor()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _CookieExtractorTest extends _ExtractorTestCase
{
    public function testExtraction()
    {
        // a real JWT
        $token = $this->token();
        // create a bunch of test requests
        $default = (new RequestFactory())->createRequest('GET', '/');
        $happyRequest = $default->withCookieParams(['token' => $token]); // the happy path
        $aliasRequest = $default->withCookieParams(['foo' => $token]); // a correct token with different name

        // the default extractor checks the `token` cookie
        $x = Manipulators::cookieExtractor();
        Assert::same($token, $x($happyRequest));
        Assert::same(null, $x($aliasRequest));
        Assert::same(null, $x($default));

        // different cookie name
        $x = Manipulators::cookieExtractor('foo');
        Assert::same(null, $x($happyRequest));
        Assert::same($token, $x($aliasRequest));
        Assert::same(null, $x($default));
    }

    public function testLogging()
    {
        // create a bunch of test requests
        $request = (new RequestFactory())->createRequest('GET', '/')->withCookieParams(['token' => $this->token()]);

        // should log if the token is found
        Manipulators::cookieExtractor('token')($request, $this->proxyLogger(function (
            $level,
            $message,
            $context
        ) {
            Assert::same(LogLevel::DEBUG, $level);
            Assert::same("Using bare token from cookie 'token'.", $message);
            Assert::same([], $context);
        }));

        // does not log when no token is found
        Manipulators::cookieExtractor('sorry')($request, $this->proxyLogger(function () {
            throw new LogicException('This should never be called.');
        }));
    }
}

// run the test
(new _CookieExtractorTest)->run();
