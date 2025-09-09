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

use Http\Client\Common\Plugin\Journal;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class History implements Journal
{
    private ?ResponseInterface $lastResponse = null;

    #[Override]
    public function addFailure(RequestInterface $request, ClientExceptionInterface $exception): void {}

    #[Override]
    public function addSuccess(RequestInterface $request, ResponseInterface $response): void
    {
        $this->lastResponse = $response;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }
}
