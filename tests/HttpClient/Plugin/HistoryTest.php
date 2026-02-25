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

namespace Esi\IPQuery\Tests\HttpClient\Plugin;

use Esi\IPQuery\HttpClient\Plugin\History;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(History::class)]
final class HistoryTest extends TestCase
{
    public function testAddSuccess(): void
    {
        $request  = self::createStub(RequestInterface::class);
        $response = self::createStub(ResponseInterface::class);

        $history = new History();

        // Initially null
        self::assertNull($history->getLastResponse());

        // After success, should return the response
        $history->addSuccess($request, $response);
        self::assertSame($response, $history->getLastResponse());

        // Adding another success should overwrite
        $response2 = self::createStub(ResponseInterface::class);
        $history->addSuccess($request, $response2);
        self::assertSame($response2, $history->getLastResponse());
    }

    public function testHistory(): void
    {
        $history = new History();
        $history->addFailure(self::createStub(RequestInterface::class), self::createStub(ClientExceptionInterface::class));

        self::assertNull($history->getLastResponse());
    }
}
