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

use Esi\IPQuery\Util;
//use InvalidArgumentException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Util::class)]
final class UtilTest extends TestCase
{
    #[DataProvider('invalidFormatProvider')]
    public function testIsValidFormatWithInvalidFormats(string $format): void
    {
        self::assertFalse(Util::isValidFormat($format));
    }

    #[DataProvider('validFormatProvider')]
    public function testIsValidFormatWithValidFormats(string $format): void
    {
        self::assertTrue(Util::isValidFormat($format));
    }

    #[DataProvider('invalidIpProvider')]
    public function testIsValidIpWithInvalidIps(string $ip): void
    {
        self::assertFalse(Util::isValidIp($ip));
    }

    #[DataProvider('validIpProvider')]
    public function testIsValidIpWithValidIps(string $ip): void
    {
        self::assertTrue(Util::isValidIp($ip));
    }

    public function testMaxAmountOfIpsConstant(): void
    {
        self::assertSame(10_000, Util::MaxAmountOfIps);
    }

    public function testValidatedThrottleOptionsWithAllCustomOptions(): void
    {
        $options = [
            'id'       => 'test-limiter',
            'policy'   => 'sliding_window',
            'limit'    => 10,
            'interval' => '1 minute',
        ];

        $result = Util::validatedThrottleOptions($options);

        self::assertSame($options, $result);
    }

    public function testValidatedThrottleOptionsWithEmptyArray(): void
    {
        $result = Util::validatedThrottleOptions([]);

        $expected = [
            'id'       => 'ipquery',
            'policy'   => 'fixed_window',
            'limit'    => 2,
            'interval' => '3 seconds',
        ];

        self::assertSame($expected, $result);
    }

    public function testValidatedThrottleOptionsWithNull(): void
    {
        $result = Util::validatedThrottleOptions(null);

        $expected = [
            'id'       => 'ipquery',
            'policy'   => 'fixed_window',
            'limit'    => 2,
            'interval' => '3 seconds',
        ];

        self::assertSame($expected, $result);
    }

    public function testValidatedThrottleOptionsWithPartialOptions(): void
    {
        $options = [
            'id'    => 'custom-id',
            'limit' => 5,
        ];

        $result = Util::validatedThrottleOptions($options);

        $expected = [
            'id'       => 'custom-id',
            'limit'    => 5,
            'policy'   => 'fixed_window',
            'interval' => '3 seconds',
        ];

        self::assertSame($expected, $result);
    }

    public function testValidFormatsConstant(): void
    {
        $expectedFormats = ['json', 'xml', 'yaml'];
        self::assertSame(Util::ValidFormats, $expectedFormats);
    }

    /**
     * @return \Iterator<string, array<string>>
     */
    public static function invalidFormatProvider(): iterable
    {
        yield 'csv' => ['csv'];
        yield 'html' => ['html'];
        yield 'txt' => ['txt'];
        yield 'empty string' => [''];
        yield 'random string' => ['invalid'];
        yield 'uppercase json' => ['JSON'];
        yield 'mixed case xml' => ['XML'];
    }

    /**
     * @return \Iterator<string, array<string>>
     */
    public static function invalidIpProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'text' => ['not-an-ip'];
        yield 'IPv4 out of range' => ['256.256.256.256'];
        yield 'IPv4 incomplete' => ['192.168.1'];
        yield 'IPv4 too many octets' => ['192.168.1.1.1'];
        yield 'IPv4 with letters' => ['192.168.a.1'];
        yield 'IPv6 invalid' => ['2001:0db8:85a3::8a2e::7334'];
        yield 'IPv6 too long' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334:extra'];
        yield 'mixed invalid' => ['192.168.1'];
        yield 'just numbers' => ['123456'];
        yield 'special chars' => ['192.168.1.1#'];
    }

    /**
     * @return \Iterator<string, array<string>>
     */
    public static function validFormatProvider(): iterable
    {
        yield 'json' => ['json'];
        yield 'xml' => ['xml'];
        yield 'yaml' => ['yaml'];
    }

    /**
     * @return \Iterator<string, array<string>>
     */
    public static function validIpProvider(): iterable
    {
        yield 'IPv4 localhost' => ['127.0.0.1'];
        yield 'IPv4 public' => ['8.8.8.8'];
        yield 'IPv4 private' => ['192.168.1.1'];
        yield 'IPv4 edge case' => ['255.255.255.255'];
        yield 'IPv4 zero' => ['0.0.0.0'];
        yield 'IPv6 localhost' => ['::1'];
        yield 'IPv6 full' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        yield 'IPv6 compressed' => ['2001:db8:85a3::8a2e:370:7334'];
        yield 'IPv6 short' => ['::'];
        yield 'IPv6 mixed' => ['::ffff:192.168.1.1'];
    }
}
