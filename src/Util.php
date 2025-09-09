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

abstract class Util
{
    public const int MaxAmountOfIps = 10_000;

    public const array ValidFormats = ['json', 'xml', 'yaml'];

    public static function isValidFormat(string $format): bool
    {
        return \in_array($format, self::ValidFormats, true);
    }

    public static function isValidIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
        );
    }

    /**
     * @param ?array{id?: string, policy?: 'fixed_window'|'sliding_window'|'token_bucket', limit?: int, interval?: string} $throttleOptions
     *
     * @return array{id: string, policy: 'fixed_window'|'sliding_window'|'token_bucket', limit: int, interval: string}
     */
    public static function validatedThrottleOptions(?array $throttleOptions): array
    {
        if ($throttleOptions === null) {
            return ['id' => 'ipquery', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '3 seconds'];
        }

        $throttleOptions['id'] ??= 'ipquery';
        $throttleOptions['policy'] ??= 'fixed_window';
        $throttleOptions['limit'] ??= 2;
        $throttleOptions['interval'] ??= '3 seconds';

        return $throttleOptions;
    }
}
