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

namespace Esi\IPQuery\Api;

use Esi\IPQuery\Client;
use Esi\IPQuery\Util;
use Http\Client\Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

use function implode;
use function str_contains;

abstract class AbstractApi
{
    private const string UriPrefix = '/';

    /**
     * Create a new API instance.
     */
    public function __construct(private readonly Client $client) {}

    /**
     * @param array<string>|string $query
     * @param 'json'|'xml'|'yaml'  $format
     *
     * @throws Exception
     */
    protected function get(array|string $query, string $format = 'json'): ResponseInterface
    {
        return $this->client->getHttpClient()->get($this->prepareUri($query, $format));
    }

    /**
     * @param array<string>|string $query
     *
     * @throws InvalidArgumentException If an invalid format, invalid IP address, or too many IP's are provided.
     */
    private function prepareUri(array|string $query, string $format): string
    {
        if (\is_array($query)) {
            $query = implode(',', $query);
        }

        $this->validateIpAddresses($query);

        if (!Util::isValidFormat($format)) {
            throw new InvalidArgumentException(\sprintf('Invalid format "%s" provided, must be one of %s', $format, implode(', ', Util::ValidFormats)));
        }

        return \sprintf('%s%s?format=%s', self::UriPrefix, $query, $format);
    }

    /**
     * @param array<string>|string $ip
     *
     * @throws InvalidArgumentException If an invalid IP address or too many IP's are provided.
     */
    private function validateIpAddresses(array|string $ip): void
    {
        if (!\is_array($ip)) {
            $ip = str_contains($ip, ',') ? explode(',', $ip) : [$ip];
        }

        $numIps = \count($ip);

        if ($numIps > Util::MaxAmountOfIps) {
            throw new InvalidArgumentException(\sprintf('Too many IP addresses provided. The limit is %d, %d provided', Util::MaxAmountOfIps, $numIps));
        }

        foreach ($ip as $address) {
            if (!Util::isValidIp($address)) {
                throw new InvalidArgumentException(\sprintf('Invalid IP address: %s', $address));
            }
        }
    }
}
