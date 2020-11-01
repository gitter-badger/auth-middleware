<?php

declare(strict_types=1);

namespace Dakujem\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * A general-purpose middleware
 * that will conditionally terminate pipeline execution
 * by calling error handler on predicate failure.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class PredicateMiddleware implements MiddlewareInterface
{
    /** @var callable fn(Request):bool */
    private $predicate;
    private Handler $errorHandler;

    public function __construct(
        callable $predicate,
        Handler $errorHandler
    ) {
        $this->predicate = $predicate;
        $this->errorHandler = $errorHandler;
    }

    /**
     * PSR-15 middleware processing.
     *
     * @param Request $request
     * @param Handler $next
     * @return Response
     */
    public function process(Request $request, Handler $next): Response
    {
        if (($this->predicate)($request)) {
            // if the predicate passes, invoke the next middleware
            return $next->handle($request);
        }
        // if the predicate fails, invoke the error handler instead
        return $this->errorHandler->handle($request);
    }
}
