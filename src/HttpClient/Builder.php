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

namespace Esi\IPQuery\HttpClient;

use Http\Client\Common\HttpMethodsClient;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\CachePlugin;
use Http\Client\Common\PluginClientFactory;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * The HTTP client builder class.
 */
final class Builder
{
    private ?CachePlugin $cachePlugin = null;

    private readonly ClientInterface $httpClient;

    private ?HttpMethodsClientInterface $httpMethodsClient = null;

    /**
     * @var array<Plugin>
     */
    private array $plugins = [];

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly UriFactoryInterface $uriFactory;

    /**
     * Create a new client builder instance.
     */
    public function __construct(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UriFactoryInterface $uriFactory = null
    ) {
        $this->httpClient     = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory  = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->uriFactory     = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
    }

    /**
     * @template T
     *
     * @param array<array-key, T> $config
     */
    public function addCache(CacheItemPoolInterface $cacheItemPool, array $config = []): void
    {
        $this->cachePlugin       = CachePlugin::clientCache($cacheItemPool, $this->streamFactory, $config);
        $this->httpMethodsClient = null;
    }

    public function addPlugin(Plugin $plugin): void
    {
        $this->plugins[]         = $plugin;
        $this->httpMethodsClient = null;
    }

    public function getHttpClient(): HttpMethodsClientInterface
    {
        if (\is_null($this->httpMethodsClient)) {
            $plugins = $this->plugins;

            if (!\is_null($this->cachePlugin)) {
                $plugins[] = $this->cachePlugin;
            }

            $this->httpMethodsClient = new HttpMethodsClient(
                (new PluginClientFactory())->createClient($this->httpClient, $plugins),
                $this->requestFactory,
                $this->streamFactory
            );
        }

        return $this->httpMethodsClient;
    }

    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function getStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    public function getUriFactory(): UriFactoryInterface
    {
        return $this->uriFactory;
    }

    public function removeCache(): void
    {
        $this->cachePlugin       = null;
        $this->httpMethodsClient = null;
    }

    /**
     * @param class-string<Plugin> $pluginClassName
     */
    public function removePlugin(string $pluginClassName): void
    {
        $pluginRemoved = false;

        foreach ($this->plugins as $key => $plugin) {
            if ($plugin instanceof $pluginClassName) {
                unset($this->plugins[$key]);

                $pluginRemoved = true;
            }
        }

        if ($pluginRemoved) {
            $this->httpMethodsClient = null;
        }
    }
}
