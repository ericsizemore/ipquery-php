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

namespace Esi\IPQuery\Tests\Api;

use Esi\IPQuery\Api\AbstractApi;
use Esi\IPQuery\Api\IP;
use Esi\IPQuery\Client;
use Esi\IPQuery\HttpClient\Builder;
use Esi\IPQuery\Util;
use Http\Client\Common\HttpMethodsClientInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;

use function random_int;

/**
 * @internal
 */
#[CoversClass(IP::class)]
#[CoversClass(Client::class)]
#[CoversClass(Builder::class)]
#[CoversClass(AbstractApi::class)]
#[CoversClass(Util::class)]
final class IPTest extends TestCase
{
    private static string $mockData = <<<'DATA'
        {
            "ip":"1.1.1.1",
            "isp": {
                "asn":"AS13335",
                "org":"Cloudflare, Inc.",
                "isp":"Cloudflare, Inc."
            },
            "location": {
                "country":"Australia",
                "country_code":"AU",
                "city":"Sydney",
                "state":"New South Wales",
                "zipcode":"1001",
                "latitude":-33.854548400186665,
                "longitude":151.20016200912815,
                "timezone":"Australia/Sydney",
                "localtime":"2025-09-05T17:02:42"
            },
            "risk": {
                "is_mobile":false,
                "is_vpn":false,
                "is_tor":false,
                "is_proxy":false,
                "is_datacenter":true,
                "risk_score":0
            }
        }
        DATA;

    public function testInvalidFormat(): void
    {
        $client = new Client();
        $ip     = new IP($client);

        $this->expectException(InvalidArgumentException::class);

        $ip->sendRequest(['1.1.1.1', '8.8.8.8'], 'toml');
    }

    public function testInvalidIp(): void
    {
        $client = new Client();
        $ip     = new IP($client);

        $this->expectException(InvalidArgumentException::class);

        $ip->sendRequest('102.92', 'toml');
    }

    public function testPrepareUriWithArray(): void
    {
        // Create a concrete implementation for testing
        $client = new Client();
        $api    = new class ($client) extends AbstractApi {
            /**
             * @param array<string>|string $query
             */
            public function testPrepareUri(array|string $query, string $format = 'json'): string
            {
                // Expose the private method for testing
                $reflectionClass  = new ReflectionClass(parent::class);
                $reflectionMethod = $reflectionClass->getMethod('prepareUri');

                /**
                 * @phpstan-var string $invokedValue
                 */
                $invokedValue = $reflectionMethod->invoke($this, $query, $format);

                return $invokedValue;
            }
        };

        $result = $api->testPrepareUri(['192.168.1.1', '10.0.0.1']);
        self::assertSame('/192.168.1.1,10.0.0.1?format=json', $result);
    }

    public function testPrepareUriWithDifferentFormats(): void
    {
        $client = new Client();
        $api    = new class ($client) extends AbstractApi {
            /**
             * @param array<string>|string $query
             */
            public function testPrepareUri(array|string $query, string $format = 'json'): string
            {
                $reflectionClass  = new ReflectionClass(parent::class);
                $reflectionMethod = $reflectionClass->getMethod('prepareUri');

                /**
                 * @phpstan-var string $invokedValue
                 */
                $invokedValue = $reflectionMethod->invoke($this, $query, $format);

                return $invokedValue;
            }
        };

        self::assertSame('/192.168.1.1?format=xml', $api->testPrepareUri('192.168.1.1', 'xml'));
        self::assertSame('/192.168.1.1?format=yaml', $api->testPrepareUri('192.168.1.1', 'yaml'));
    }

    public function testSendRequestWithMultipleIPs(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn(self::$mockData);
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockClient = $this->createMock(HttpMethodsClientInterface::class);
        $mockClient->expects(self::once())
            ->method('get')
            ->with('/192.168.1.1,10.0.0.1?format=json')
            ->willReturn($mockResponse);

        $client = $this->createMock(Client::class);
        $client->method('getHttpClient')->willReturn($mockClient);

        $ip     = new IP($client);
        $result = $ip->sendRequest(['192.168.1.1', '10.0.0.1']);

        self::assertSame(self::$mockData, $result);
    }

    public function testSendRequestWithXmlFormat(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockStream   = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn('<xml>data</xml>');
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockClient = $this->createMock(HttpMethodsClientInterface::class);
        $mockClient->expects(self::once())
            ->method('get')
            ->with('/192.168.1.1?format=xml')
            ->willReturn($mockResponse);

        $client = $this->createMock(Client::class);
        $client->method('getHttpClient')->willReturn($mockClient);

        $ip     = new IP($client);
        $result = $ip->sendRequest('192.168.1.1', 'xml');

        self::assertSame('<xml>data</xml>', $result);
    }

    public function testShouldReturnData(): void
    {
        $mockObject = $this->getApiMock();
        $mockObject->expects(self::once())
            ->method('sendRequest')
            ->with('1.1.1.1')
            ->willReturn(self::$mockData);
        /**
         * @psalm-var MockObject&IP $mockObject
         */
        self::assertSame(self::$mockData, $mockObject->sendRequest('1.1.1.1'));
    }

    public function testTooManyIps(): void
    {
        $client = new Client();
        $ip     = new IP($client);

        $this->expectException(InvalidArgumentException::class);

        $ips     = [];
        $ipCount = 0;

        while ($ipCount <= Util::MaxAmountOfIps) {
            $ips[] = $this->generateRandomIp();
            ++$ipCount;
        }

        $ip->sendRequest($ips);
    }

    private function generateRandomIp(): string
    {
        return \sprintf('%d.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(0, 255), random_int(0, 255));
    }

    private function getApiMock(): MockObject
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->getMock();
        $httpClient->method('sendRequest');

        $client = Client::createWithHttpClient($httpClient);

        return $this->getMockBuilder(IP::class)
            ->onlyMethods(['sendRequest', 'get'])
            ->setConstructorArgs([$client])
            ->getMock();
    }
}
