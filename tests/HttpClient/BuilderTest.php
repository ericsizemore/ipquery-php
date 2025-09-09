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

namespace Esi\IPQuery\Tests\HttpClient;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Esi\IPQuery\HttpClient\Builder;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\CachePlugin;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use ReflectionClass;

/**
 * @internal
 *
 * @psalm-suppress MissingConstructor
 */
#[CoversClass(Builder::class)]
final class BuilderTest extends TestCase
{
    private Builder $builder;

    /**
     * @psalm-api
     */
    #[Before]
    public function initBuilder(): void
    {
        $this->builder = new Builder(
            $this->createMock(ClientInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(UriFactoryInterface::class),
        );
    }

    public function testAddAndRemoveCache(): void
    {
        $local               = new Local(sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'clientCacheTest' . \DIRECTORY_SEPARATOR);
        $filesystem          = new Filesystem($local);
        $filesystemCachePool = new FilesystemCachePool($filesystem);

        $this->builder->addCache($filesystemCachePool);

        $reflectionClass        = new ReflectionClass($this->builder);
        $reflectionProperty     = $reflectionClass->getProperty('cachePlugin');
        $reflectionPluginClient = $reflectionClass->getProperty('httpMethodsClient');

        self::assertInstanceOf(CachePlugin::class, $reflectionProperty->getValue($this->builder));
        self::assertNull($reflectionPluginClient->getValue($this->builder));

        $this->builder->removeCache();
        self::assertNull($reflectionProperty->getValue($this->builder));
        self::assertNull($reflectionPluginClient->getValue($this->builder));
    }

    public function testAddPluginShouldInvalidateHttpClient(): void
    {
        $httpMethodsClient = $this->builder->getHttpClient();

        $this->builder->addPlugin($this->createMock(Plugin::class));

        self::assertNotSame($httpMethodsClient, $this->builder->getHttpClient());

        $local               = new Local(sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'clientCacheTest' . \DIRECTORY_SEPARATOR);
        $filesystem          = new Filesystem($local);
        $filesystemCachePool = new FilesystemCachePool($filesystem);

        $this->builder->addCache($filesystemCachePool);
        self::assertNotSame($httpMethodsClient, $this->builder->getHttpClient());
        $this->builder->removeCache();
        self::assertNotSame($httpMethodsClient, $this->builder->getHttpClient());
    }

    public function testCachePluginIntegration(): void
    {
        $local               = new Local(sys_get_temp_dir());
        $filesystem          = new Filesystem($local);
        $filesystemCachePool = new FilesystemCachePool($filesystem);

        $httpMethodsClient = $this->builder->getHttpClient();

        $this->builder->addCache($filesystemCachePool);
        $httpClientAfter = $this->builder->getHttpClient();

        self::assertNotSame($httpMethodsClient, $httpClientAfter);

        // Test that cache is actually included in the plugin chain
        $reflectionClass    = new ReflectionClass($this->builder);
        $reflectionProperty = $reflectionClass->getProperty('cachePlugin');
        self::assertNotNull($reflectionProperty->getValue($this->builder));
    }

    public function testDefaultConstructor(): void
    {
        $builder = new Builder();

        self::assertInstanceOf(HttpMethodsClientInterface::class, $builder->getHttpClient());
        self::assertInstanceOf(RequestFactoryInterface::class, $builder->getRequestFactory());
        self::assertInstanceOf(StreamFactoryInterface::class, $builder->getStreamFactory());
        self::assertInstanceOf(UriFactoryInterface::class, $builder->getUriFactory());
    }

    public function testHttpClientShouldBeAnHttpMethodsClient(): void
    {
        self::assertInstanceOf(HttpMethodsClientInterface::class, $this->builder->getHttpClient());
    }

    public function testRemoveMultiplePluginsOfSameType(): void
    {
        $plugin1 = $this->createMock(Plugin::class);
        $plugin2 = $this->createMock(Plugin::class);

        $this->builder->addPlugin($plugin1);
        $this->builder->addPlugin($plugin2);

        $httpMethodsClient = $this->builder->getHttpClient();

        $this->builder->removePlugin(Plugin::class);

        self::assertNotSame($httpMethodsClient, $this->builder->getHttpClient());
    }

    public function testRemoveNonExistentPlugin(): void
    {
        $httpMethodsClient = $this->builder->getHttpClient();

        // Should not affect anything if plugin doesn't exist
        $this->builder->removePlugin('NonExistentPlugin');

        self::assertSame($httpMethodsClient, $this->builder->getHttpClient());
    }

    public function testRemovePluginShouldInvalidateHttpClient(): void
    {
        $this->builder->addPlugin($this->createMock(Plugin::class));

        $httpMethodsClient = $this->builder->getHttpClient();

        $this->builder->removePlugin(Plugin::class);

        self::assertNotSame($httpMethodsClient, $this->builder->getHttpClient());
    }

    public function testRequestFactoryShouldBeARequestFactory(): void
    {
        self::assertInstanceOf(RequestFactoryInterface::class, $this->builder->getRequestFactory());
    }

    public function testStreamFactoryShouldBeAStreamFactory(): void
    {
        self::assertInstanceOf(StreamFactoryInterface::class, $this->builder->getStreamFactory());
    }

    public function testUriFactoryShouldBeAStreamFactory(): void
    {
        self::assertInstanceOf(UriFactoryInterface::class, $this->builder->getUriFactory());
    }
}
