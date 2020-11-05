<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test;

require_once __DIR__ . '/bootstrap.php';

use Dakujem\Middleware\TokenManipulators;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Tester\Assert;
use Tester\TestCase;

/**
 * Test of TokenManipulators::errorMessagePassJson static factory.
 *
 * @see TokenManipulators::errorMessagePassJson()
 *
 * @author Andrej Rypak (dakujem) <xrypak@gmail.com>
 */
class _ErrorMessagePassTest extends TestCase
{
    /** @noinspection PhpComposerExtensionStubsInspection */
    public function testErrorPass()
    {
        $req = (new RequestFactory())->createRequest('GET', '/')
            ->withAttribute('token.error', 'An error message for you.')
            ->withAttribute('foobar', 'A foobar for you.');

        /** @var ResponseInterface $res */
        $res = TokenManipulators::errorMessagePassJson()($req, (new ResponseFactory())->createResponse());
        // omg, why the need to rewind??
        $res->getBody()->rewind();
        Assert::equal(
            (object)[
                'error' => (object)[
                    'message' => 'An error message for you.',
                ],
            ],
            json_decode($res->getBody()->getContents())
        );
        Assert::same('application/json', implode(';', $res->getHeader('Content-type')));

        $res = TokenManipulators::errorMessagePassJson('foobar')($req, (new ResponseFactory())->createResponse());
        $res->getBody()->rewind();
        Assert::equal(
            (object)[
                'error' => (object)[
                    'message' => 'A foobar for you.',
                ],
            ],
            json_decode($res->getBody()->getContents())
        );

        $res = TokenManipulators::errorMessagePassJson('something.else')($req, (new ResponseFactory())->createResponse());
        $res->getBody()->rewind();
        Assert::equal(
            (object)[
                'error' => (object)[
                    'message' => 'No token found.', // assume no token found
                ],
            ],
            json_decode($res->getBody()->getContents())
        );
    }
}

// run the test
(new _ErrorMessagePassTest)->run();
