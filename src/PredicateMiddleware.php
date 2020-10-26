<?php

declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * A general-purpose middleware
 * that will conditionally terminate pipeline execution by calling error responder
 * on predicate failure.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class PredicateMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $predicate;
    /** @var callable */
    private $errorResponder;

    public function __construct(
        callable $predicate,
        callable $errorResponder
    ) {
        $this->predicate = $predicate;
        $this->errorResponder = $errorResponder;
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
        // if the predicate fails, invoke the error responder
        return ($this->errorResponder)($request);
    }

    /**
     * Create a basic error responder that returns a response with 403 status.
     *
     * @param ResponseFactoryInterface $responseFactory
     * @return callable
     */
    public static function basicErrorResponder(ResponseFactoryInterface $responseFactory): callable
    {
        return function () use ($responseFactory): Response {
            return $responseFactory->createResponse(403);
        };
    }
}
