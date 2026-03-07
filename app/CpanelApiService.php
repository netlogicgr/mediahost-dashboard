<?php

declare(strict_types=1);

final class CpanelApiService
{
    /** @return array<string,mixed> */
    public function fetchServerStats(array $server): array
    {
        $baseUrl = rtrim((string) $server['host'], '/');
        $type = (string) ($server['auth_type'] ?? 'whm');

        if ($type === 'cpanel') {
            $serverInfo = $this->request($baseUrl . '/execute/ServerInformation/get_information', $server);
            $resourceInfo = $this->request($baseUrl . '/execute/ResourceUsage/get_usages', $server);

            return [
                'cpu' => $this->extractCpu($serverInfo, $resourceInfo),
                'ram' => $this->extractRam($serverInfo, $resourceInfo),
                'disk' => $this->extractDisk($serverInfo, $resourceInfo),
                'io' => $this->extractIo($serverInfo, $resourceInfo),
                'raw' => ['server_info' => $serverInfo, 'resource_usage' => $resourceInfo],
            ];
        }

        $serverInfo = $this->requestFirstSuccessful($server, [
            $baseUrl . '/json-api/get_system_info?api.version=1',
            $baseUrl . '/json-api/version?api.version=1',
            $baseUrl . '/json-api/myprivs?api.version=1',
        ]);

        $whmResponses = $this->fetchWhmResponses($baseUrl, $server);
        $resourceInfo = [
            'systemloadavg' => $whmResponses['systemloadavg'] ?? [],
            'get_disk_usage' => $whmResponses['get_disk_usage'] ?? [],
            'servicestatus' => $whmResponses['servicestatus'] ?? [],
        ];

        return [
            'cpu' => $this->extractWhmCpu($resourceInfo) ?? $this->extractCpu($serverInfo, $resourceInfo),
            'ram' => $this->extractRam($serverInfo, $resourceInfo),
            'disk' => $this->extractWhmDisk($resourceInfo) ?? $this->extractDisk($serverInfo, $resourceInfo),
            'io' => $this->extractWhmIo($resourceInfo) ?? $this->extractIo($serverInfo, $resourceInfo),
            'raw' => ['server_info' => $serverInfo, 'resource_usage' => $resourceInfo, 'whm' => $whmResponses],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function fetchWhmResponses(string $baseUrl, array $server): array
    {
        $responses = [];
        $endpoints = [
            'systemloadavg' => '/json-api/systemloadavg?api.version=1',
            'get_disk_usage' => '/json-api/get_disk_usage?api.version=1',
            'servicestatus' => '/json-api/servicestatus?api.version=1',
            'version' => '/json-api/version?api.version=1',
        ];

        foreach ($endpoints as $key => $path) {
            $responses[$key] = $this->request($baseUrl . $path, $server);
        }

        return $responses;
    }

    /**
     * @param array<int,string> $urls
     * @return array<string,mixed>
     */
    private function requestFirstSuccessful(array $server, array $urls): array
    {
        $lastError = 'No API endpoints configured.';

        foreach ($urls as $url) {
            try {
                return $this->request($url, $server);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError);
    }

    /** @return array<string,mixed> */
    private function request(string $url, array $server): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                $this->buildAuthorizationHeader($server),
                'Accept: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            throw new RuntimeException('API request failed: ' . ($error ?: 'HTTP ' . $code));
        }

        error_log(sprintf('WHM API raw response [%s]: %s', $url, (string) $raw));

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid API response.');
        }

        return $decoded;
    }

    private function buildAuthorizationHeader(array $server): string
    {
        $type = $server['auth_type'] ?? 'whm';
        $username = $server['username'] ?? 'root';
        $token = (string) $server['api_token'];

        if ($type === 'cpanel') {
            return sprintf('Authorization: cpanel %s:%s', $username, $token);
        }

        return sprintf('Authorization: whm %s:%s', $username, $token);
    }

    private function extractCpu(array $serverInfo, array $resourceInfo): ?float
    {
        return $this->findNumericValue(['cpu_usage', 'cpu', 'loadavg', 'oneminute', 'cpu_percent'], [$resourceInfo, $serverInfo]);
    }

    private function extractRam(array $serverInfo, array $resourceInfo): ?float
    {
        $value = $this->findNumericValue(['memory_used_percent', 'memory_usage', 'memory_used', 'ram_usage'], [$resourceInfo, $serverInfo]);
        if ($value !== null && $value > 1 && $value <= 100) {
            return $value;
        }
        return $value;
    }

    private function extractDisk(array $serverInfo, array $resourceInfo): ?float
    {
        return $this->findNumericValue(['disk_used_percent', 'disk_usage', 'disk_used', 'filesystem_percent'], [$resourceInfo, $serverInfo]);
    }

    private function extractIo(array $serverInfo, array $resourceInfo): ?float
    {
        return $this->findNumericValue(['io_usage', 'io_percent', 'iops', 'io_wait'], [$resourceInfo, $serverInfo]);
    }

    private function extractWhmCpu(array $resourceInfo): ?float
    {
        $loads = $this->findNumericValuesByKeyContains($resourceInfo['systemloadavg'] ?? [], ['one', '1min', 'loadavg']);
        return $loads[0] ?? null;
    }

    private function extractWhmDisk(array $resourceInfo): ?float
    {
        $diskPayload = $resourceInfo['get_disk_usage'] ?? [];
        $flat = $this->flatten($diskPayload);

        $used = null;
        $total = null;

        foreach ($flat as $key => $value) {
            $number = $this->toFloat($value);
            if ($number === null) {
                continue;
            }

            if ($used === null && stripos($key, 'used') !== false) {
                $used = $number;
            }

            if ($total === null && (stripos($key, 'total') !== false || stripos($key, 'size') !== false || stripos($key, 'capacity') !== false)) {
                $total = $number;
            }

            if (stripos($key, 'percent') !== false) {
                return $number;
            }
        }

        if ($used !== null && $total !== null && $total > 0) {
            return ($used / $total) * 100;
        }

        return null;
    }

    private function extractWhmIo(array $resourceInfo): ?float
    {
        $services = $this->findNumericValuesByKeyContains($resourceInfo['servicestatus'] ?? [], ['running', 'active']);
        if ($services === []) {
            return null;
        }

        $running = 0;
        foreach ($services as $value) {
            if ($value > 0) {
                $running++;
            }
        }

        return (float) (($running / count($services)) * 100);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $needles
     * @return array<int,float>
     */
    private function findNumericValuesByKeyContains(array $source, array $needles): array
    {
        $values = [];
        foreach ($this->flatten($source) as $key => $value) {
            foreach ($needles as $needle) {
                if (stripos($key, $needle) === false) {
                    continue;
                }

                $number = $this->toFloat($value);
                if ($number !== null) {
                    $values[] = $number;
                }
                break;
            }
        }

        return $values;
    }

    /** @param array<int,array<string,mixed>> $sources */
    private function findNumericValue(array $keys, array $sources): ?float
    {
        foreach ($sources as $source) {
            $flat = $this->flatten($source);
            foreach ($flat as $key => $value) {
                foreach ($keys as $needle) {
                    if (stripos($key, $needle) !== false) {
                        $number = $this->toFloat($value);
                        if ($number !== null) {
                            return $number;
                        }
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('/-?\d+(\.\d+)?/', $value, $m)) {
            return (float) $m[0];
        }
        return null;
    }
}
