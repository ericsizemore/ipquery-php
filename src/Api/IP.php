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

use Http\Client\Exception;

class IP extends AbstractApi
{
    /**
     * Get information about one or more IP addresses.
     *
     * @param array<string>|string $ip     One or more IP addresses (comma-separated)
     * @param 'json'|'xml'|'yaml'  $format Response format (default: json)
     *
     * @throws Exception
     */
    public function sendRequest(array|string $ip, string $format = 'json'): string
    {
        $response = $this->get($ip, $format);

        return (string) $response->getBody();
    }
}
