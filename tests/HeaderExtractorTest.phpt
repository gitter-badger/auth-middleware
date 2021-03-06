<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/support/ExtractorTestCase.php';

use Dakujem\Middleware\TokenManipulators;
use Dakujem\Middleware\Test\Support\_ExtractorTestCase;
use LogicException;
use Psr\Log\LogLevel;
use Slim\Psr7\Factory\RequestFactory;
use Tester\Assert;

/**
 * Test of the TokenManipulators::headerExtractor static factory.
 *
 * @see TokenManipulators::headerExtractor()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _HeaderExtractorTest extends _ExtractorTestCase
{
    public function testExtraction()
    {
        // a real JWT
        $token = $this->token();
        // create a bunch of test requests
        $default = (new RequestFactory())->createRequest('GET', '/');
        $happyRequest = $default->withHeader('Authorization', 'Bearer ' . $token); // the happy path
        $invalidHeaderRequest = $default->withHeader('Authorization', 'foo'); // invalid header format
        $aliasRequest = $default->withHeader('foo', 'Bearer ' . $token); // a correct token with different name
        $fooRequest = $default->withHeader('foo', 'foo value'); // correct header not set

        // the default extractor only checks the `Authorization` header
        $x = TokenManipulators::headerExtractor();
        Assert::same($token, $x($happyRequest));
        Assert::same(null, $x($invalidHeaderRequest));
        Assert::same(null, $x($aliasRequest));
        Assert::same(null, $x($fooRequest));

        // different header
        $x = TokenManipulators::headerExtractor('foo');
        Assert::same(null, $x($happyRequest));
        Assert::same(null, $x($invalidHeaderRequest));
        Assert::same($token, $x($aliasRequest));
        Assert::same(null, $x($fooRequest));
    }

    public function testFormats()
    {
        $x = TokenManipulators::headerExtractor();
        $default = (new RequestFactory())->createRequest('GET', '/');

        Assert::same('OK', $x($default->withHeader('Authorization', 'Bearer OK')));
        Assert::same('OK', $x($default->withHeader('Authorization', 'Bearer               OK          '))); // is trimmed
        Assert::same('ok', $x($default->withHeader('authorization', 'bearer ok'))); // case insensitive
        Assert::null($x($default->withHeader('Authorization', 'Bearer')));
        Assert::null($x($default->withHeader('Authorization', 'Bearer ')));
        Assert::null($x($default->withHeader('Authorization', 'OK')));
        Assert::null($x($default->withHeader('Authorization', '.')));
        Assert::same('čučoriedky', $x($default->withHeader('Authorization', 'Bearer čučoriedky')));
        Assert::same('(ňä§)', $x($default->withHeader('Authorization', 'Bearer (ňä§)')));
    }

    public function testLogging()
    {
        // create a bunch of test requests
        $request = (new RequestFactory())->createRequest('GET', '/');

        $x = TokenManipulators::headerExtractor();

        // should log if the token is found
        $x($request->withHeader('Authorization', 'Bearer ' . $this->token()), $this->proxyLogger(function (
            $level,
            $message,
            $context
        ) {
            Assert::same(LogLevel::DEBUG, $level);
            Assert::same("Using Bearer token from request header 'Authorization'.", $message);
            Assert::same([], $context);
        }));

        // should log if the header is malformed
        $x($request->withHeader('Authorization', 'foobar'), $this->proxyLogger(function (
            $level,
            $message,
            $context
        ) {
            Assert::same(LogLevel::DEBUG, $level);
            Assert::same("Bearer token not present in request header 'Authorization'.", $message);
            Assert::same([], $context);
        }));

        // does not log when the header is not present
        $x($request, $this->proxyLogger(function () {
            throw new LogicException('This should never be called.');
        }));
    }
}

// run the test
(new _HeaderExtractorTest)->run();
