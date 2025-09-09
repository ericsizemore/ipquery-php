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
use Override;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * @internal
 */
final class ExceptionThrower implements Plugin
{
    #[Override]
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        return $next($request)->then(static function (ResponseInterface $response): ResponseInterface {
            $status = $response->getStatusCode();

            if ($status >= 400 && $status < 600) {
                throw new RuntimeException($response->getReasonPhrase(), $status);
            }

            return $response;
        });
    }
}
