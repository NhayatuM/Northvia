<?php

declare(strict_types=1);

namespace Northvia\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware stack processor
 */
class MiddlewareStack
{
    private array $middleware = [];

    /**
     * Add middleware to the stack
     */
    public function add($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Process request through middleware stack
     */
    public function process(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        $middleware = array_reverse($this->middleware);
        
        $next = $handler;
        
        foreach ($middleware as $m) {
            $next = function($request) use ($m, $next) {
                if (is_string($m)) {
                    $m = new $m();
                }
                
                return $m->process($request, $next);
            };
        }
        
        return $next($request);
    }
}