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
use PHPUnit\Framework\MockObject\MockObject;
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
        $limiter     = $this->createMock(LimiterInterface::class);
        $rateLimiter = new RateLimiter($limiter, 5, 10.5);

        self::assertInstanceOf(RateLimiter::class, $rateLimiter);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $limiter     = $this->createMock(LimiterInterface::class);
        $rateLimiter = new RateLimiter($limiter);

        self::assertInstanceOf(RateLimiter::class, $rateLimiter);
    }

    public function testHandleRequestCallsNextAfterReserve(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter);

        $nextCalled = false;
        $next       = static function ($request) use ($response, &$nextCalled): HttpFulfilledPromise {
            $nextCalled = true;
            return new HttpFulfilledPromise($response);
        };

        $first = static fn ($request): HttpFulfilledPromise => new HttpFulfilledPromise($response);

        $promise = $rateLimiter->handleRequest($request, $next, $first);
        $promise->wait();

        self::assertTrue($nextCalled, 'next() should have been called');
    }

    public function testHandleRequestCallsReserveWithCorrectParameters(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);
        $reservation->expects(self::once())->method('wait');

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(2, 5.0)
            ->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter, 2, 5.0);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $promise = $rateLimiter->handleRequest($request, $next, $first);
        $result  = $promise->wait();

        self::assertSame($response, $result);
    }

    public function testHandleRequestReturnsPromise(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('reserve')->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $promise = $rateLimiter->handleRequest($request, $next, $first);

        self::assertInstanceOf(Promise::class, $promise);
    }

    public function testHandleRequestThrowsInvalidArgumentException(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(100, null)
            ->willThrowException(new InvalidArgumentException('Tokens exceed burst size'));

        $rateLimiter = new RateLimiter($limiter, 100);

        $httpFulfilledPromise = new HttpFulfilledPromise($this->createMock(ResponseInterface::class));
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tokens exceed burst size');

        $rateLimiter->handleRequest($request, $next, $first);
    }

    public function testHandleRequestThrowsMaxWaitDurationExceededException(): void
    {
        $request = $this->createMock(RequestInterface::class);

        // Create a real RateLimit for the exception
        $rateLimiterFactory = new RateLimiterFactory([
            'id'       => 'test',
            'policy'   => 'fixed_window',
            'limit'    => 1,
            'interval' => '1 second',
        ], new InMemoryStorage());
        $rateLimit = $rateLimiterFactory->create('test-exception')->consume(1);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, 5.0)
            ->willThrowException(new MaxWaitDurationExceededException('Max wait time exceeded', $rateLimit));

        $rateLimiter = new RateLimiter($limiter, 1, 5.0);

        $httpFulfilledPromise = new HttpFulfilledPromise($this->createMock(ResponseInterface::class));
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $this->expectException(MaxWaitDurationExceededException::class);
        $this->expectExceptionMessage('Max wait time exceeded');

        $rateLimiter->handleRequest($request, $next, $first);
    }

    public function testHandleRequestThrowsReserveNotSupportedException(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(2, null)
            ->willThrowException(new ReserveNotSupportedException('Reserve not supported'));

        $rateLimiter = new RateLimiter($limiter, 2);

        $httpFulfilledPromise = new HttpFulfilledPromise($this->createMock(ResponseInterface::class));
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $this->expectException(ReserveNotSupportedException::class);
        $this->expectExceptionMessage('Reserve not supported');

        $rateLimiter->handleRequest($request, $next, $first);
    }

    public function testHandleRequestWithDefaultParameters(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);
        $reservation->expects(self::once())->method('wait');

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, null)
            ->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $promise = $rateLimiter->handleRequest($request, $next, $first);
        $result  = $promise->wait();

        self::assertSame($response, $result);
    }

    public function testHandleRequestWithZeroMaxTime(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(1, 0.0)
            ->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter, 1, 0.0);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $promise = $rateLimiter->handleRequest($request, $next, $first);

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testHandleRequestWithZeroTokens(): void
    {
        $request  = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        /**
         * @var MockObject&Reservation $reservation
         */
        $reservation = $this->createMock(Reservation::class);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter
            ->expects(self::once())
            ->method('reserve')
            ->with(0, null)
            ->willReturn($reservation);

        $rateLimiter = new RateLimiter($limiter, 0);

        $httpFulfilledPromise = new HttpFulfilledPromise($response);
        $next                 = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;
        $first                = static fn ($request): HttpFulfilledPromise => $httpFulfilledPromise;

        $promise = $rateLimiter->handleRequest($request, $next, $first);

        self::assertInstanceOf(Promise::class, $promise);
        self::assertSame($response, $promise->wait());
    }

    public function testIntegrationWithRealRateLimiter(): void
    {
        // Integration test using real rate limiter with generous limits
        $rateLimiterFactory = new RateLimiterFactory([
            'id'       => 'test',
            'policy'   => 'fixed_window',
            'limit'    => 100, // Very high limit to avoid actual throttling in tests
            'interval' => '1 minute',
        ], new InMemoryStorage());

        $limiter     = $rateLimiterFactory->create('integration-test');
        $rateLimiter = new RateLimiter($limiter);

        $mockClient   = new Client(new Psr17Factory());
        $pluginClient = new PluginClient($mockClient, [$rateLimiter]);

        // This should work without throttling due to generous limits
        $response = $pluginClient->sendRequest(new Request('GET', 'http://example.com'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testMultipleRequestsWithRealRateLimiter(): void
    {
        // Test multiple requests work with generous limits
        $rateLimiterFactory = new RateLimiterFactory([
            'id'       => 'test',
            'policy'   => 'fixed_window',
            'limit'    => 50,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        $limiter     = $rateLimiterFactory->create('multi-test');
        $rateLimiter = new RateLimiter($limiter);

        $mockClient   = new Client(new Psr17Factory());
        $pluginClient = new PluginClient($mockClient, [$rateLimiter]);

        // Send multiple requests - they should all succeed with generous limits
        for ($i = 0; $i < 5; ++$i) {
            $response = $pluginClient->sendRequest(new Request('GET', 'http://example.com/test-' . $i));
            self::assertSame(200, $response->getStatusCode());
        }
    }
}
