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

namespace Esi\IPQuery\Tests;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Esi\IPQuery\Client;
use Esi\IPQuery\HttpClient\Builder;
use Esi\IPQuery\HttpClient\Plugin\History;
use Esi\IPQuery\HttpClient\Plugin\RateLimiter;
use Esi\IPQuery\Util;
use Http\Client\Common\HttpMethodsClient;
use Http\Client\Common\Plugin\CachePlugin;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionProperty;

/**
 * @internal
 */
#[CoversClass(Client::class)]
#[UsesClass(Builder::class)]
#[UsesClass(History::class)]
#[UsesClass(RateLimiter::class)]
#[UsesClass(Util::class)]
final class ClientTest extends TestCase
{
    public function testAddAndRemoveCache(): void
    {
        $local      = new Local(sys_get_temp_dir());
        $filesystem = new Filesystem($local);
        $pool       = new FilesystemCachePool($filesystem);

        $client = new Client();
        $client->addCache($pool);

        $httpClientBuilder = $this->getPrivateProperty($client, 'httpClientBuilder');
        $cachePlugin       = $this->getPrivateProperty($httpClientBuilder, 'cachePlugin');
        $pool              = $this->getPrivateProperty($cachePlugin, 'pool');

        self::assertNotNull($cachePlugin);
        self::assertInstanceOf(FilesystemCachePool::class, $pool);

        $client->removeCache();

        $httpClientBuilder = $this->getPrivateProperty($client, 'httpClientBuilder');
        $cachePlugin       = $this->getPrivateProperty($httpClientBuilder, 'cachePlugin');

        self::assertNull($cachePlugin);
    }

    /**
     * @todo This test needs fleshed out more.
     */
    public function testConstructorWithCustomBuilder(): void
    {
        $builder = new Builder();
        $client  = new Client($builder);

        self::assertInstanceOf(Client::class, $client);
        // Verify the builder was used correctly
    }

    public function testConstructorWithCustomThrottleOptions(): void
    {
        $customOptions = [
            'id'       => 'custom-test',
            'policy'   => 'sliding_window',
            'limit'    => 10,
            'interval' => '5 seconds',
        ];

        $client = new Client(
            throttle: true,
            throttleOptions: $customOptions
        );

        self::assertInstanceOf(Client::class, $client);
    }

    public function testConstructorWithThrottleEnabled(): void
    {
        $throttleOptions = [
            'id'       => 'test-throttle',
            'policy'   => 'fixed_window',
            'limit'    => 5,
            'interval' => '10 seconds',
        ];

        $client = new Client(
            throttle: true,
            throttleOptions: $throttleOptions
        );

        self::assertInstanceOf(Client::class, $client);

        // You can also verify the HTTP client was built correctly
        $httpMethodsClient = $client->getHttpClient();
        self::assertInstanceOf(HttpMethodsClient::class, $httpMethodsClient);
    }

    public function testConstructorWithThrottleEnabledDefaultOptions(): void
    {
        $client = new Client(throttle: true);

        self::assertInstanceOf(Client::class, $client);
        self::assertInstanceOf(HttpMethodsClient::class, $client->getHttpClient());
    }

    public function testCreateClient(): void
    {
        $client = new Client();

        self::assertInstanceOf(Client::class, $client);
        self::assertInstanceOf(HttpMethodsClient::class, $client->getHttpClient());
    }

    public function testCreateWithHttpClient(): void
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->getMock();
        $httpClient
            ->method('sendRequest');

        $client = Client::createWithHttpClient($httpClient);

        self::assertInstanceOf(Client::class, $client);
        self::assertInstanceOf(HttpMethodsClient::class, $client->getHttpClient());
    }

    /**
     * @todo This test needs fleshed out further
     */
    public function testGetLastResponse(): void
    {
        $client = new Client();

        // Initially should be null
        self::assertNull($client->getLastResponse());

        // After making a request, should return the response
        // Will need to mock an actual HTTP call here
    }

    public function testGetStreamFactory(): void
    {
        $client = new Client();
        self::assertInstanceOf(StreamFactoryInterface::class, $client->getStreamFactory());
    }

    private function getPrivateProperty(
        null|AbstractCachePool|Builder|CachePlugin|Client $object,
        string $propertyName
    ): null|AbstractCachePool|Builder|CachePlugin {
        if ($object === null) {
            return null;
        }

        $reflectionProperty = new ReflectionProperty($object, $propertyName);

        /**
         * @psalm-var null|AbstractCachePool|Builder|CachePlugin $value
         */
        $value = $reflectionProperty->getValue($object);

        return $value;
    }
}
