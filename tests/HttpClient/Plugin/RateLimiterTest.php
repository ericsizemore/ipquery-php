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

use Esi\IPQuery\HttpClient\Plugin\RateLimiter;
use Http\Client\Common\PluginClient;
use Http\Client\Promise\HttpFulfilledPromise;
use Http\Mock\Client;
use Http\Promise\Promise;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * @internal
 */
#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase
{
    public function testConstructorWithCustomValues(): void
    {
        self::assertInstanceOf(
            RateLimiter::class,
            new RateLimiter(self::createStub(LimiterInterface::class), 5, 10.5)
        );
    }

    public function testConstructorWithDefaultValues(): void
    {
        self::assertInstanceOf(
            RateLimiter::class,
            new RateLimiter(self::createStub(LimiterInterface::class))
        );
    }

    public function testHandleRequestCallsNextAfterReserve(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = self::createStub(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->willReturn($reservation);

        $nextCalled = false;

        new RateLimiter($limiter)->handleRequest(
            self::createStub(RequestInterface::class),
            static function (RequestInterface $request) use ($response, &$nextCalled): HttpFulfilledPromise {
                $nextCalled = true;

                return new HttpFulfilledPromise($response);
            },
            static fn (RequestInterface $request): HttpFulfilledPromise => new HttpFulfilledPromise($response)
        )->wait();

        self::assertTrue($nextCalled, 'next() should have been called');
    }

    public function testHandleRequestCallsReserveWithCorrectParameters(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = $this->createMock(Reservation::class);
        $reservation->expects(self::once())->method('wait');

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(2, 5.0)
            ->willReturn($reservation);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $result = new RateLimiter($limiter, 2, 5.0)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            )->wait();

        self::assertSame($response, $result);
    }

    public function testHandleRequestReturnsPromise(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = self::createStub(Reservation::class);

        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('reserve')->willReturn($reservation);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $promise = new RateLimiter($limiter)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            );

        self::assertInstanceOf(Promise::class, $promise);
    }

    public function testHandleRequestThrowsInvalidArgumentException(): void
    {
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(100, null)
            ->willThrowException(new InvalidArgumentException('Tokens exceed burst size'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tokens exceed burst size');

        $httpFulfilledPromise = new HttpFulfilledPromise(self::createStub(ResponseInterface::class));

        new RateLimiter($limiter, 100)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            );
    }

    public function testHandleRequestThrowsMaxWaitDurationExceededException(): void
    {
        // Create a real RateLimit for the exception

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, 5.0)
            ->willThrowException(new MaxWaitDurationExceededException('Max wait time exceeded', new RateLimiterFactory([
                'id'       => 'test',
                'policy'   => 'fixed_window',
                'limit'    => 1,
                'interval' => '1 second',
            ], new InMemoryStorage())->create('test-exception')->consume(1)));

        $this->expectException(MaxWaitDurationExceededException::class);
        $this->expectExceptionMessage('Max wait time exceeded');

        $httpFulfilledPromise = new HttpFulfilledPromise(self::createStub(ResponseInterface::class));

        new RateLimiter($limiter, 1, 5.0)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            );
    }

    public function testHandleRequestThrowsReserveNotSupportedException(): void
    {
        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(2, null)
            ->willThrowException(new ReserveNotSupportedException('Reserve not supported'));

        $this->expectException(ReserveNotSupportedException::class);
        $this->expectExceptionMessage('Reserve not supported');

        $httpFulfilledPromise = new HttpFulfilledPromise(self::createStub(ResponseInterface::class));

        new RateLimiter($limiter, 2)->handleRequest(
            self::createStub(RequestInterface::class),
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        );
    }

    public function testHandleRequestWithDefaultParameters(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = $this->createMock(Reservation::class);
        $reservation->expects(self::once())->method('wait');

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, null)
            ->willReturn($reservation);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $result = new RateLimiter($limiter)->handleRequest(
            self::createStub(RequestInterface::class),
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
            static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
        )->wait();

        self::assertSame($response, $result);
    }

    public function testHandleRequestWithZeroMaxTime(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = self::createStub(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, 0.0)
            ->willReturn($reservation);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $promise = new RateLimiter($limiter, 1, 0.0)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            );

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testHandleRequestWithZeroTokens(): void
    {
        $response    = self::createStub(ResponseInterface::class);
        $reservation = self::createStub(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(0, null)
            ->willReturn($reservation);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);

        $promise = new RateLimiter($limiter, 0)
            ->handleRequest(
                self::createStub(RequestInterface::class),
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise,
                static fn (RequestInterface $request): HttpFulfilledPromise => $httpFulfilledPromise
            );

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testIntegrationWithRealRateLimiter(): void
    {
        // Integration test using real rate limiter with generous limits
        // This should work without throttling due to generous limits

        $response = new PluginClient(
            new Client(new Psr17Factory()),
            [
                new RateLimiter(
                    new RateLimiterFactory(
                        [
                            'id'       => 'test',
                            'policy'   => 'fixed_window',
                            'limit'    => 100, // Very high limit to avoid actual throttling in tests
                            'interval' => '1 minute',
                        ],
                        new InMemoryStorage()
                    )->create('integration-test')
                ),
            ]
        )->sendRequest(new Request('GET', 'http://example.com'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testMultipleRequestsWithRealRateLimiter(): void
    {
        // Test multiple requests work with generous limits

        $pluginClient = new PluginClient(
            new Client(new Psr17Factory()),
            [
                new RateLimiter(
                    new RateLimiterFactory(
                        [
                            'id'       => 'test',
                            'policy'   => 'fixed_window',
                            'limit'    => 50,
                            'interval' => '1 minute',
                        ],
                        new InMemoryStorage()
                    )->create('multi-test')
                ),
            ]
        );

        // Send multiple requests - they should all succeed with generous limits
        for ($i = 0; $i < 5; ++$i) {
            $response = $pluginClient->sendRequest(new Request('GET', 'http://example.com/test-' . $i));
            self::assertSame(200, $response->getStatusCode());
        }
    }
}
