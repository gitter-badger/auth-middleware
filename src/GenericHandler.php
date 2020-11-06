<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Generic PSR-15 request handler.
 * Turns any callable with signature `fn(Request):Response` into a PSR `RequestHandlerInterface` implementation.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class GenericHandler implements Handler
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(Request $request): Response
    {
        return ($this->callable)($request);
    }

    public function __invoke(Request $request): Response
    {
        return $this->handle($request);
    }
}
