<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Generic PSR-15 middleware.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class GenericMiddleware implements Middleware
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(Request $request, Handler $next): Response
    {
        return ($this->callable)($request, $next);
    }

    public function __invoke(Request $request, $next): Response
    {
        return $this->process($request, $next instanceof Handler ? $next : new GenericHandler($next));
    }
}
