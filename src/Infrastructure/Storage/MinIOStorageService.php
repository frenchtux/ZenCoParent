<?php
declare(strict_types=1);

namespace ZenCoParent\Infrastructure\Storage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ZenCoParent\Domain\Storage\FileStorageInterface;

final class MinIOStorageService implements FileStorageInterface
{
    private Client $http;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region = 'us-east-1',
    ) {
        $this->http = new Client(['base_uri' => rtrim($endpoint, '/') . '/']);
    }

    public function upload(string $key, string $content, string $mimeType): void
    {
        $now         = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp   = $now->format('Ymd\THis\Z');
        $date        = $now->format('Ymd');
        $uri         = '/' . $this->bucket . '/' . ltrim($key, '/');
        $payloadHash = hash('sha256', $content);

        $headers = $this->buildHeaders($timestamp, $payloadHash, $mimeType);
        $headers['Authorization'] = $this->buildAuthorization(
            'PUT', $uri, '', $headers, $payloadHash, $timestamp, $date
        );

        try {
            $this->http->put(ltrim($uri, '/'), [
                'headers' => $headers,
                'body'    => $content,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("MinIO upload failed for key '{$key}': " . $e->getMessage(), 0, $e);
        }
    }

    public function getPublicUrl(string $key): string
    {
        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($key, '/');
    }

    public function delete(string $key): void
    {
        $now         = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp   = $now->format('Ymd\THis\Z');
        $date        = $now->format('Ymd');
        $uri         = '/' . $this->bucket . '/' . ltrim($key, '/');
        $payloadHash = hash('sha256', '');

        $headers = $this->buildHeaders($timestamp, $payloadHash, 'application/octet-stream');
        unset($headers['Content-Type']);
        $headers['Authorization'] = $this->buildAuthorization(
            'DELETE', $uri, '', $headers, $payloadHash, $timestamp, $date
        );

        try {
            $this->http->delete(ltrim($uri, '/'), ['headers' => $headers]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("MinIO delete failed for key '{$key}': " . $e->getMessage(), 0, $e);
        }
    }

    // ─── Signature V4 helpers ─────────────────────────────────────────────────

    private function buildHeaders(string $timestamp, string $payloadHash, string $mimeType): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        if ($port !== null) {
            $host .= ':' . $port;
        }

        return [
            'Host'                 => $host,
            'Content-Type'         => $mimeType,
            'X-Amz-Date'           => $timestamp,
            'X-Amz-Content-Sha256' => $payloadHash,
        ];
    }

    private function buildAuthorization(
        string $method,
        string $uri,
        string $queryString,
        array  $headers,
        string $payloadHash,
        string $timestamp,
        string $date,
    ): string {
        // Build canonical headers (lowercase key, sorted)
        $canonicalHeaders = '';
        $signedHeadersList = [];
        $sorted = $headers;
        ksort($sorted);
        foreach ($sorted as $key => $value) {
            $lower = strtolower($key);
            $canonicalHeaders .= $lower . ':' . trim((string) $value) . "\n";
            $signedHeadersList[] = $lower;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalRequest = implode("\n", [
            $method,
            $uri,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey  = $this->deriveSigningKey($date);
        $signature   = hash_hmac('sha256', $stringToSign, $signingKey);

        return "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, " .
               "SignedHeaders={$signedHeaders}, Signature={$signature}";
    }

    private function deriveSigningKey(string $date): string
    {
        $kDate    = hash_hmac('sha256', $date,          'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region,  $kDate,                    true);
        $kService = hash_hmac('sha256', 's3',           $kRegion,                  true);
        return      hash_hmac('sha256', 'aws4_request', $kService,                 true);
    }
}
