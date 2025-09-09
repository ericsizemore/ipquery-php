<?php

declare(strict_types=1);

/**
 * This file is part of Esi\IPQuery.
 *
 * (c) Eric Sizemore <admin@secondversion.com>
 *
 * This source file is subject to the MIT license. For the full copyright and
 * license information, please view the LICENSE file that was distributed with
 * this source code.
 */

namespace Esi\IPQuery\HttpClient\Plugin;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;

final readonly class RateLimiter implements Plugin
{
    /**
     * @param int    $tokens  The amount of tokens required.
     * @param ?float $maxTime Maximum accepted waiting time, in seconds.
     */
    public function __construct(
        private LimiterInterface $rateLimiter,
        private int $tokens = 1,
        private ?float $maxTime = null
    ) {}

    /**
     * @throws MaxWaitDurationExceededException if $maxTime is set and the process needs to wait longer than its value
     * @throws ReserveNotSupportedException     if this limiter implementation doesn't support reserving tokens
     * @throws InvalidArgumentException         if $tokens is larger than the maximum burst size
     */
    #[Override]
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $this->rateLimiter->reserve($this->tokens, $this->maxTime)->wait();

        return $next($request);
    }
}
