<?php

declare(strict_types=1);

namespace Tests\E2E;

use CURLFile;

/**
 * Minimal cookie-aware HTTP client (one instance = one browser session).
 */
final class HttpClient
{
    private string $cookieJar;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieJar = (string) tempnam(sys_get_temp_dir(), 'e2e-cookies');
    }

    public function __destruct()
    {
        @unlink($this->cookieJar);
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string,mixed> $data form fields (CURLFile values switch to multipart)
     */
    public function post(string $path, array $data = []): HttpResponse
    {
        return $this->request('POST', $path, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function postJson(string $path, array $data): HttpResponse
    {
        return $this->request('POST', $path, [], json_encode($data, JSON_THROW_ON_ERROR), ['Content-Type: application/json']);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $headers
     */
    public function request(string $method, string $path, array $data = [], ?string $rawBody = null, array $headers = []): HttpResponse
    {
        $curl = curl_init($this->baseUrl . $path);
        $responseHeaders = '';
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $responseHeaders .= $line;

                return strlen($line);
            },
        ]);

        if ($method !== 'GET') {
            $hasFile = array_filter($data, static fn ($value) => $value instanceof CURLFile) !== [];
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody ?? ($hasFile ? $data : http_build_query($data)));
        }

        $body = (string) curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return new HttpResponse(
            $status,
            $body,
            $this->header($responseHeaders, 'Location'),
            $this->header($responseHeaders, 'Content-Type'),
        );
    }

    private function header(string $raw, string $name): string
    {
        return preg_match('/^' . $name . ':\s*(.+?)\r?$/mi', $raw, $m) === 1 ? trim($m[1]) : '';
    }
}
