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
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $promise = new ExceptionThrower()->handleRequest(
            self::createStub(RequestInterface::class),
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        );

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testHandleRequestWithDifferentErrorCodes(): void
    {
        $statusCodes   = [401, 403, 404, 500, 503];
        $reasonPhrases = ['Unauthorized', 'Forbidden', 'Not Found', 'Internal Server Error', 'Service Unavailable'];

        foreach ($statusCodes as $index => $statusCode) {
            $response = self::createStub(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($statusCode);
            $response->method('getReasonPhrase')->willReturn($reasonPhrases[$index]);

            $httpFulfilledPromise = new HttpFulfilledPromise($response);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($reasonPhrases[$index]);
            $this->expectExceptionCode($statusCode);

            try {
                new ExceptionThrower()->handleRequest(
                    self::createStub(RequestInterface::class),
                    static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                    static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
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
        $stream = self::createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getBody')->willReturn($stream);
        $response->method('getReasonPhrase')->willReturn('Bad Request');

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad Request');

        new ExceptionThrower()->handleRequest(
            self::createStub(RequestInterface::class),
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        )->wait();
    }

    public function testHandleRequestWithRedirectStatus(): void
    {
        // Test that 3xx codes don't throw exceptions
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(302);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $result = new ExceptionThrower()->handleRequest(
            self::createStub(RequestInterface::class),
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        )->wait();

        self::assertSame($response, $result);
    }
}
