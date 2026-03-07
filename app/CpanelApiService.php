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

        $resourceInfo = $this->requestFirstSuccessful($server, [
            $baseUrl . '/json-api/loadavg?api.version=1',
            $baseUrl . '/json-api/systemloadavg?api.version=1',
            $baseUrl . '/json-api/getdiskusage?api.version=1',
        ]);

        return [
            'cpu' => $this->extractCpu($serverInfo, $resourceInfo),
            'ram' => $this->extractRam($serverInfo, $resourceInfo),
            'disk' => $this->extractDisk($serverInfo, $resourceInfo),
            'io' => $this->extractIo($serverInfo, $resourceInfo),
            'raw' => ['server_info' => $serverInfo, 'resource_usage' => $resourceInfo],
        ];
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
