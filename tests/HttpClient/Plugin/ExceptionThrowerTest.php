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

use Esi\IPQuery\HttpClient\Plugin\ExceptionThrower;
use Http\Client\Promise\HttpFulfilledPromise;
use Http\Promise\Promise;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(ExceptionThrower::class)]
final class ExceptionThrowerTest extends TestCase
{
    public function testHandleRequest(): void
    {
        $exceptionThrower = new ExceptionThrower();
        $request          = $this->createMock(RequestInterface::class);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $promise = $exceptionThrower->handleRequest(
            $request,
            static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise
        );

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testHandleRequestWithDifferentErrorCodes(): void
    {
        $exceptionThrower = new ExceptionThrower();
        $request          = $this->createMock(RequestInterface::class);

        $statusCodes   = [401, 403, 404, 500, 503];
        $reasonPhrases = ['Unauthorized', 'Forbidden', 'Not Found', 'Internal Server Error', 'Service Unavailable'];

        foreach ($statusCodes as $index => $statusCode) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($statusCode);
            $response->method('getReasonPhrase')->willReturn($reasonPhrases[$index]);

            $httpFulfilledPromise = new HttpFulfilledPromise($response);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($reasonPhrases[$index]);
            $this->expectExceptionCode($statusCode);

            try {
                $exceptionThrower->handleRequest(
                    $request,
                    static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                    static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
                )->wait();
            } catch (RuntimeException $e) {
                self::assertSame($statusCode, $e->getCode());
                self::assertSame($reasonPhrases[$index], $e->getMessage());

                if ($index === \count($statusCodes) - 1) {
                    throw $e; // Re-throw the last one for PHPUnit
                }
            }
        }
    }

    public function testHandleRequestWithError(): void
    {
        $exceptionThrower = new ExceptionThrower();
        $request          = $this->createMock(RequestInterface::class);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getBody')->willReturn($stream);
        $response->method('getReasonPhrase')->willReturn('Bad Request');

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad Request');

        $exceptionThrower->handleRequest(
            $request,
            static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        )->wait();
    }

    public function testHandleRequestWithRedirectStatus(): void
    {
        $exceptionThrower = new ExceptionThrower();
        $request          = $this->createMock(RequestInterface::class);

        // Test that 3xx codes don't throw exceptions
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(302);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $result = $exceptionThrower->handleRequest(
            $request,
            static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (MockObject&RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        )->wait();

        self::assertSame($response, $result);
    }
}
