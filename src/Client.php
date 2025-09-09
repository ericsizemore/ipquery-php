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

namespace Esi\IPQuery;

use Esi\IPQuery\HttpClient\Builder;
use Esi\IPQuery\HttpClient\Plugin\ExceptionThrower;
use Esi\IPQuery\HttpClient\Plugin\History;
use Esi\IPQuery\HttpClient\Plugin\RateLimiter;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin\AddHostPlugin;
use Http\Client\Common\Plugin\HeaderDefaultsPlugin;
use Http\Client\Common\Plugin\HistoryPlugin;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class Client
{
    private const string ApiUrl = 'https://api.ipquery.io';

    private const string UserAgent = 'esi/ipquery-php v1.0.0 (https://github.com/ericsizemore/ipquery-php)';

    private readonly History $responseHistory;

    /**
     * Instantiate a new IPQuery client.
     *
     * Symfony's rate limiter implements some of the most common policies to enforce rate limits: fixed window, sliding window, token bucket.
     * To enforce rate limits, set $throttle to true and pass along any options to $throttleOptions.
     *
     * Valid options for $throttleOptions:
     *
     * 'id' => string
     * 'policy' => 'fixed_window'|'sliding_window'|'token_bucket'
     * 'limit' => integer
     * 'interval' => a number followed by any of the units accepted by the PHP date relative formats (e.g. 3 seconds, 10 hours, 1 day, etc.)
     *
     * @see https://symfony.com/doc/current/rate_limiter.html
     * @see https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative
     *
     * @param ?array{id: string, policy: 'fixed_window'|'sliding_window'|'token_bucket', limit: int, interval: string} $throttleOptions
     */
    public function __construct(
        private readonly Builder $httpClientBuilder = new Builder(),
        private readonly bool $throttle = false,
        private readonly ?array $throttleOptions = null,
    ) {
        $this->responseHistory = new History();

        $this->httpClientBuilder->addPlugin(new ExceptionThrower());
        $this->httpClientBuilder->addPlugin(new HistoryPlugin($this->responseHistory));
        $this->httpClientBuilder->addPlugin(new HeaderDefaultsPlugin([
            'User-Agent' => self::UserAgent,
        ]));

        if ($this->throttle) {
            $this->httpClientBuilder->addPlugin(new RateLimiter(
                (new RateLimiterFactory(
                    Util::validatedThrottleOptions($this->throttleOptions),
                    new InMemoryStorage(),
                ))->create(),
            ));
        }

        $this->setApiUrl(self::ApiUrl);
    }

    /**
     * @template T
     *
     * @param array<string, T> $config
     */
    public function addCache(CacheItemPoolInterface $cacheItemPool, array $config = []): void
    {
        $this->httpClientBuilder->addCache($cacheItemPool, $config);
    }

    public function getHttpClient(): HttpMethodsClientInterface
    {
        return $this->httpClientBuilder->getHttpClient();
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->responseHistory->getLastResponse();
    }

    public function getStreamFactory(): StreamFactoryInterface
    {
        return $this->httpClientBuilder->getStreamFactory();
    }

    public function removeCache(): void
    {
        $this->httpClientBuilder->removeCache();
    }

    public function setApiUrl(string $url): void
    {
        $this->httpClientBuilder->removePlugin(AddHostPlugin::class);
        $this->httpClientBuilder->addPlugin(new AddHostPlugin(
            $this->httpClientBuilder->getUriFactory()->createUri($url)
        ));
    }

    public static function createWithHttpClient(ClientInterface $httpClient): self
    {
        return new self(new Builder($httpClient));
    }
}
