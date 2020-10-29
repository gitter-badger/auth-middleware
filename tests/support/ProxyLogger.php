<?php

declare(strict_types=1);

namespace Dakujem\Middleware\Test\Support;

use Psr\Log\AbstractLogger;

/**
 * ProxyLogger
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class _ProxyLogger extends AbstractLogger
{
    private $fn;

    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    public function log($level, $message, array $context = [])
    {
        ($this->fn)($level, $message, $context);
    }
}
