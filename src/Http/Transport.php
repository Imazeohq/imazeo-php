<?php

declare(strict_types=1);

namespace Imazeo\Http;

use Imazeo\Errors\ErrorMapper;
use Imazeo\Errors\ImazeoError;
use Imazeo\Errors\NetworkError;
use Imazeo\Types\BinaryResult;
use Imazeo\Version;

final class TransportConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly string $apiVersion,
        public readonly int $timeoutMs,
        public readonly string $userAgent,
    ) {
    }
}

/**
 * @phpstan-type JsonMeta array{
 *   requestId: string,
 *   page?: int,
 *   limit?: int,
 *   total?: int,
 *   pages?: int
 * }
 * @phpstan-type JsonSuccess array{data: mixed, meta: JsonMeta}
 */
final class Transport
{
    public function __construct(
        public readonly TransportConfig $config,
        private readonly HttpHandlerInterface $handler,
    ) {
    }

    /**
     * @param array{
     *   apiKey: string,
     *   baseUrl?: string,
     *   apiVersion?: string,
     *   timeoutMs?: int,
     *   appName?: string,
     * } $input
     */
    public static function createConfig(array $input): TransportConfig
    {
        $apiKey = trim($input['apiKey'] ?? '');
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        if (strlen($apiKey) > 128) {
            throw new \InvalidArgumentException('apiKey is too long');
        }

        $apiVersion = $input['apiVersion'] ?? null;
        $apiVersion = is_string($apiVersion) && $apiVersion !== ''
            ? $apiVersion
            : Version::DEFAULT_API_VERSION;
        self::assertApiVersion($apiVersion);

        $baseUrlRaw = $input['baseUrl'] ?? null;
        $baseUrl = self::normalizeBaseUrl(
            is_string($baseUrlRaw) && $baseUrlRaw !== ''
                ? $baseUrlRaw
                : Version::DEFAULT_BASE_URL,
        );
        $timeoutMs = $input['timeoutMs'] ?? null;
        $timeoutMs = is_int($timeoutMs) ? $timeoutMs : Version::DEFAULT_TIMEOUT_MS;
        if ($timeoutMs < 1) {
            throw new \InvalidArgumentException('timeoutMs must be a positive number');
        }

        $appName = $input['appName'] ?? null;
        $ua = is_string($appName) && $appName !== ''
            ? 'Imazeo-PHP/' . Version::SDK_VERSION . ' ' . $appName
            : 'Imazeo-PHP/' . Version::SDK_VERSION;

        return new TransportConfig(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            apiVersion: $apiVersion,
            timeoutMs: $timeoutMs,
            userAgent: substr($ua, 0, 256),
        );
    }

    public static function assertApiVersion(string $value): void
    {
        if (!in_array($value, Version::SUPPORTED_API_VERSIONS, true)) {
            throw new \InvalidArgumentException(
                'Unsupported apiVersion "' . $value . '". Supported: '
                . implode(', ', Version::SUPPORTED_API_VERSIONS),
            );
        }
    }

    public static function normalizeBaseUrl(string $raw): string
    {
        $parts = parse_url($raw);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid baseUrl: ' . $raw);
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('baseUrl must use http or https');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('baseUrl must not include credentials');
        }

        $path = $parts['path'] ?? '';
        $path = rtrim($path, '/');
        if ($path === '/v1') {
            $path = '';
        }

        $origin = $scheme . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        if ($path !== '' && $path !== '/') {
            return rtrim($origin . $path, '/');
        }

        return $origin;
    }

    /**
     * @param list<string> $segments
     */
    public function path(array $segments): string
    {
        $joined = implode('/', array_map(
            static fn (string $s): string => str_replace('%2F', '/', rawurlencode($s)),
            $segments,
        ));

        return $this->config->baseUrl . '/' . $this->config->apiVersion . '/' . $joined;
    }

    /**
     * @param array<string, string> $headers
     * @return array{data: mixed, meta: array{requestId: string, page?: int, limit?: int, total?: int, pages?: int}}
     */
    public function requestJson(
        string $method,
        string $url,
        mixed $body = null,
        array $headers = [],
        ?int $timeoutMs = null,
    ): array {
        $reqHeaders = array_merge([
            'Accept' => 'application/json',
            'X-API-Key' => $this->config->apiKey,
            'User-Agent' => $this->config->userAgent,
        ], $headers);

        $rawBody = null;
        if ($body !== null) {
            $reqHeaders['Content-Type'] = 'application/json';
            $encoded = json_encode($body, JSON_THROW_ON_ERROR);
            $rawBody = $encoded;
        }

        $response = $this->send($method, $url, $reqHeaders, $rawBody, $timeoutMs);

        return $this->parseJsonEnvelope($response);
    }

    /**
     * @param array<string, string> $headers
     */
    public function requestBinary(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        ?int $timeoutMs = null,
    ): BinaryResult {
        $reqHeaders = array_merge([
            'Accept' => '*/*',
            'X-API-Key' => $this->config->apiKey,
            'User-Agent' => $this->config->userAgent,
        ], $headers);

        $response = $this->send($method, $url, $reqHeaders, $body, $timeoutMs);

        if ($response['status'] === 304) {
            return $this->binaryFromResponse($response, notModified: true, ifNoneMatch: $headers['If-None-Match'] ?? null);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw $this->errorFromResponse($response);
        }

        return $this->binaryFromResponse($response);
    }

    /**
     * @param list<array{field: string, upload: PreparedUpload}> $files
     * @param array<string, string> $headers
     * @return array{data: mixed, meta: array{requestId: string, page?: int, limit?: int, total?: int, pages?: int}}
     */
    public function requestMultipartJson(
        string $method,
        string $url,
        array $files,
        ?int $uploadLength = null,
        array $headers = [],
        ?int $timeoutMs = null,
    ): array {
        $multipart = Multipart::build($files);
        $reqHeaders = array_merge([
            'Accept' => 'application/json',
            'X-API-Key' => $this->config->apiKey,
            'User-Agent' => $this->config->userAgent,
            'Content-Type' => $multipart['contentType'],
        ], $headers);

        if ($uploadLength !== null) {
            $reqHeaders['X-Upload-Length'] = (string) $uploadLength;
        }

        $response = $this->send($method, $url, $reqHeaders, $multipart['body'], $timeoutMs);

        return $this->parseJsonEnvelope($response);
    }

    /**
     * @param list<array{field: string, upload: PreparedUpload}> $files
     * @param array<string, string> $headers
     * @return BinaryResult|array{data: mixed, meta: array{requestId: string, page?: int, limit?: int, total?: int, pages?: int}}
     */
    public function requestMultipartBinary(
        string $method,
        string $url,
        array $files,
        ?int $uploadLength = null,
        array $headers = [],
        ?int $timeoutMs = null,
    ): BinaryResult|array {
        $multipart = Multipart::build($files);
        $reqHeaders = array_merge([
            'Accept' => '*/*',
            'X-API-Key' => $this->config->apiKey,
            'User-Agent' => $this->config->userAgent,
            'Content-Type' => $multipart['contentType'],
        ], $headers);

        if ($uploadLength !== null) {
            $reqHeaders['X-Upload-Length'] = (string) $uploadLength;
        }

        $response = $this->send($method, $url, $reqHeaders, $multipart['body'], $timeoutMs);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw $this->errorFromResponse($response);
        }

        $ct = $response['headers']['content-type'] ?? '';
        if (str_contains($ct, 'application/json')) {
            return $this->parseJsonEnvelope($response);
        }

        return $this->binaryFromResponse($response);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        ?int $timeoutMs,
    ): array {
        $timeout = $timeoutMs ?? $this->config->timeoutMs;

        try {
            return $this->handler->send([
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeoutMs' => $timeout,
            ]);
        } catch (NetworkError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new NetworkError(
                $e->getMessage() !== '' ? $e->getMessage() : 'Network request failed',
                previous: $e,
            );
        }
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     * @return array{data: mixed, meta: array{requestId: string, page?: int, limit?: int, total?: int, pages?: int}}
     */
    private function parseJsonEnvelope(array $response): array
    {
        $requestId = $response['headers']['x-request-id'] ?? null;
        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw ErrorMapper::mapHttpError([
                    'status' => $response['status'],
                    'message' => 'Request failed',
                    'requestId' => $requestId,
                ]);
            }
            throw new NetworkError('Response was not valid JSON', requestId: $requestId, previous: $e);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw $this->errorFromPayload($response['status'], $payload, $requestId);
        }

        if (
            !is_array($payload)
            || ($payload['success'] ?? null) !== true
            || !array_key_exists('data', $payload)
        ) {
            throw ErrorMapper::mapHttpError([
                'status' => $response['status'],
                'message' => 'Unexpected success envelope',
                'requestId' => $requestId,
            ]);
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return [
            'data' => $payload['data'],
            'meta' => [
                'requestId' => (string) ($meta['requestId'] ?? $requestId ?? ''),
                'page' => isset($meta['page']) ? (int) $meta['page'] : null,
                'limit' => isset($meta['limit']) ? (int) $meta['limit'] : null,
                'total' => isset($meta['total']) ? (int) $meta['total'] : null,
                'pages' => isset($meta['pages']) ? (int) $meta['pages'] : null,
            ],
        ];
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     */
    private function errorFromResponse(array $response): ImazeoError
    {
        $requestId = $response['headers']['x-request-id'] ?? null;
        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ErrorMapper::mapHttpError([
                'status' => $response['status'],
                'message' => 'Request failed',
                'requestId' => $requestId,
            ]);
        }

        return $this->errorFromPayload($response['status'], $payload, $requestId);
    }

    private function errorFromPayload(int $status, mixed $payload, ?string $requestId): ImazeoError
    {
        $err = null;
        if (is_array($payload) && isset($payload['error']) && is_array($payload['error'])) {
            $err = $payload['error'];
            $metaRid = null;
            if (isset($payload['meta']) && is_array($payload['meta'])) {
                $metaRid = isset($payload['meta']['requestId'])
                    ? (string) $payload['meta']['requestId']
                    : null;
            }
            $requestId = $metaRid ?? $requestId;
        }

        return ErrorMapper::mapHttpError([
            'status' => $status,
            'code' => isset($err['code']) ? (string) $err['code'] : null,
            'message' => isset($err['message']) ? (string) $err['message'] : 'Request failed',
            'requestId' => $requestId,
            'details' => $err['details'] ?? null,
        ]);
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     */
    private function binaryFromResponse(
        array $response,
        bool $notModified = false,
        ?string $ifNoneMatch = null,
    ): BinaryResult {
        $headers = $response['headers'];

        return new BinaryResult(
            data: $notModified ? '' : $response['body'],
            contentType: $headers['content-type'] ?? 'application/octet-stream',
            requestId: $headers['x-request-id'] ?? '',
            headers: $headers,
            etag: $headers['etag'] ?? $ifNoneMatch,
            width: self::numHeader($headers, 'x-image-width'),
            height: self::numHeader($headers, 'x-image-height'),
            format: $headers['x-image-format'] ?? null,
            transformationId: $headers['x-transformation-id'] ?? null,
            cache: $headers['x-cache'] ?? null,
            pdfPage: self::numHeader($headers, 'x-pdf-page'),
            notModified: $notModified,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private static function numHeader(array $headers, string $name): ?int
    {
        if (!isset($headers[$name]) || $headers[$name] === '') {
            return null;
        }
        if (!is_numeric($headers[$name])) {
            return null;
        }

        return (int) $headers[$name];
    }
}
