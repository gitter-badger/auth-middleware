<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test\Support;

use Psr\Log\LoggerInterface;
use Tester\TestCase;

/**
 * ExtractorTestCase
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
abstract class _ExtractorTestCase extends TestCase
{
    protected function token(): string
    {
        return implode('.', [
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
            'eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ',
            'SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
        ]);
    }

    protected function proxyLogger(callable $fn): LoggerInterface
    {
        require_once __DIR__ . '/ProxyLogger.php';
        return new _ProxyLogger($fn);
    }
}
