<?php

declare(strict_types=1);

namespace Imazeo\Tests;

use Imazeo\Errors\AuthenticationError;
use Imazeo\Errors\RateLimitError;
use Imazeo\Errors\ValidationError;
use Imazeo\Http\HttpHandlerInterface;
use Imazeo\Http\Transport;
use Imazeo\Imazeo;
use PHPUnit\Framework\TestCase;

final class MockHttpHandler implements HttpHandlerInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeoutMs: int}> */
    public array $requests = [];

    /** @var list<callable(array): array{status: int, headers: array<string, string>, body: string}|array{status: int, headers: array<string, string>, body: string}> */
    private array $queue = [];

    /**
     * @param array{status: int, headers?: array<string, string>, body?: string}|callable(array): array{status: int, headers: array<string, string>, body: string} $response
     */
    public function push(array|callable $response): void
    {
        $this->queue[] = $response;
    }

    public function send(array $request): array
    {
        $this->requests[] = $request;
        if ($this->queue === []) {
            throw new \RuntimeException('MockHttpHandler: no queued responses');
        }
        $next = array_shift($this->queue);
        if (is_callable($next)) {
            return $next($request);
        }

        return [
            'status' => $next['status'],
            'headers' => $next['headers'] ?? ['content-type' => 'application/json', 'x-request-id' => 'req_test'],
            'body' => $next['body'] ?? '',
        ];
    }
}

final class ClientTest extends TestCase
{
    public function testCanonicalBarcodeContract(): void
    {
        $handler = new MockHttpHandler();
        $handler->push(function (array $request): array {
            $this->assertSame([
                'type' => 'ean8', 'content' => '0123456',
                'format' => 'svg', 'includeText' => false,
            ], json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR));
            return ['status' => 200, 'headers' => ['content-type' => 'image/svg+xml'], 'body' => '<svg/>'];
        });
        $client = new Imazeo(['apiKey' => 'test', 'projectId' => 'project', 'httpHandler' => $handler]);
        $client->barcodes->generate([
            'type' => 'ean8', 'content' => '0123456',
            'format' => 'svg', 'includeText' => false,
        ]);
    }
    public function testMergeRepeatsFilePartsInOrder(): void
    {
        $handler = new MockHttpHandler();
        $handler->push(function (array $request): array {
            $body = (string) $request['body'];
            $this->assertSame(2, substr_count($body, 'name="file"'));
            $this->assertStringNotContainsString('name="files[]"', $body);
            $first = strpos($body, '%PDF-first');
            $second = strpos($body, '%PDF-second');
            $this->assertNotFalse($first);
            $this->assertNotFalse($second);
            $this->assertTrue($first < $second);
            return ['status' => 200, 'headers' => ['content-type' => 'application/pdf'], 'body' => '%PDF-merged'];
        });
        $client = new Imazeo(['apiKey' => 'test', 'projectId' => 'project', 'httpHandler' => $handler]);
        $result = $client->pdf->merge(['%PDF-first', '%PDF-second']);
        $this->assertSame('%PDF-merged', $result->data);
    }

    public function testNormalizesBaseUrlAndStripsV1(): void
    {
        $this->assertSame(
            'https://api.example.com',
            Transport::normalizeBaseUrl('https://api.example.com/'),
        );
        $this->assertSame(
            'https://api.example.com',
            Transport::normalizeBaseUrl('https://api.example.com/v1'),
        );
        $this->expectException(\InvalidArgumentException::class);
        Transport::normalizeBaseUrl('ftp://x');
    }

    public function testRejectsUnsupportedApiVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported apiVersion/');
        new Imazeo([
            'apiKey' => 'key',
            'apiVersion' => 'v9',
            'httpHandler' => new MockHttpHandler(),
        ]);
    }

    public function testSendsApiKeyAndUserAgent(): void
    {
        $handler = new MockHttpHandler();
        $handler->push([
            'status' => 200,
            'headers' => ['content-type' => 'application/json', 'x-request-id' => 'req_test'],
            'body' => json_encode([
                'success' => true,
                'data' => [
                    'projectId' => 'proj_1',
                    'keyId' => 'key_1',
                ],
                'meta' => ['requestId' => 'req_test'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $client = new Imazeo([
            'apiKey' => 'secret-key',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $ctx = $client->context->get();
        $this->assertSame('proj_1', $ctx['projectId']);

        $req = $handler->requests[0];
        $this->assertSame('https://api.example.com/v1/api-key/context', $req['url']);
        $this->assertSame('secret-key', $req['headers']['X-API-Key']);
        $this->assertMatchesRegularExpression('#^Imazeo-PHP/1\.0\.0#', $req['headers']['User-Agent']);
        $this->assertArrayNotHasKey('Authorization', $req['headers']);
    }

    public function testResolvesProjectIdFromContextWhenOmitted(): void
    {
        $handler = new MockHttpHandler();
        $handler->push([
            'status' => 200,
            'body' => json_encode([
                'success' => true,
                'data' => [
                    'projectId' => 'proj_auto',
                    'keyId' => 'k',
                ],
                'meta' => ['requestId' => 'req_test'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $handler->push([
            'status' => 200,
            'body' => json_encode([
                'success' => true,
                'data' => [],
                'meta' => ['requestId' => 'req_test', 'page' => 1, 'limit' => 10, 'total' => 0, 'pages' => 0],
            ], JSON_THROW_ON_ERROR),
        ]);

        $client = new Imazeo([
            'apiKey' => 'k',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $page = $client->images->list(['page' => 1, 'limit' => 10]);
        $this->assertSame([], $page->items);
        $this->assertSame('req_test', $page->requestId);
        $this->assertStringContainsString('/v1/projects/proj_auto/images?', $handler->requests[1]['url']);
    }

    public function testUploadsWithXUploadLengthAndMultipartFileField(): void
    {
        $handler = new MockHttpHandler();
        $handler->push(function (array $request): array {
            $this->assertSame('4', $request['headers']['X-Upload-Length']);
            $this->assertStringContainsString('multipart/form-data', $request['headers']['Content-Type']);
            $this->assertNotNull($request['body']);
            $this->assertStringContainsString('name="file"', $request['body']);
            $this->assertStringContainsString("\x01\x02\x03\x04", $request['body']);

            return [
                'status' => 201,
                'headers' => ['content-type' => 'application/json', 'x-request-id' => 'req_test'],
                'body' => json_encode([
                    'success' => true,
                    'data' => [
                        '_id' => 'img_1',
                        'projectId' => 'p1',
                        'sourceType' => 'upload',
                        'originalName' => 'a.png',
                        'bytes' => 4,
                        'sha256' => 'abc',
                        'metadata' => [
                            'format' => 'png',
                            'mimeType' => 'image/png',
                            'width' => 1,
                            'height' => 1,
                            'frames' => 1,
                            'pixelCount' => 1,
                            'hasAlpha' => false,
                            'orientation' => null,
                        ],
                        'status' => 'ready',
                        'createdAt' => '2026-01-01T00:00:00.000Z',
                        'updatedAt' => '2026-01-01T00:00:00.000Z',
                    ],
                    'meta' => ['requestId' => 'req_test'],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p1',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $image = $client->images->upload("\x01\x02\x03\x04", [
            'filename' => 'a.png',
            'contentType' => 'image/png',
        ]);
        $this->assertSame('img_1', $image['_id']);
    }

    public function testMapsErrorEnvelopesToTypedErrors(): void
    {
        $auth = new MockHttpHandler();
        $auth->push([
            'status' => 401,
            'body' => json_encode([
                'success' => false,
                'error' => ['code' => 'API_KEY_INVALID', 'message' => 'bad key'],
                'meta' => ['requestId' => 'req_test'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $auth,
        ]);
        try {
            $client->images->get('x');
            $this->fail('Expected AuthenticationError');
        } catch (AuthenticationError $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame('API_KEY_INVALID', $e->errorCode);
        }

        $rate = new MockHttpHandler();
        $rate->push([
            'status' => 429,
            'body' => json_encode([
                'success' => false,
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => 'slow down'],
                'meta' => ['requestId' => 'req_test'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $client2 = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $rate,
        ]);
        $this->expectException(RateLimitError::class);
        $client2->images->get('x');
    }

    public function testMapsValidationErrorForQr(): void
    {
        $handler = new MockHttpHandler();
        $handler->push([
            'status' => 400,
            'body' => json_encode([
                'success' => false,
                'error' => ['code' => 'TOOL_INVALID', 'message' => 'bad'],
                'meta' => ['requestId' => 'req_test'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $this->expectException(ValidationError::class);
        $client->qr->generate(['content' => 'hi']);
    }

    public function testReturnsBinaryResultsForQrGeneration(): void
    {
        $png = "\x89PNG";
        $handler = new MockHttpHandler();
        $handler->push([
            'status' => 200,
            'headers' => [
                'content-type' => 'image/png',
                'x-request-id' => 'req_bin',
            ],
            'body' => $png,
        ]);
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $result = $client->qr->generate(['content' => 'https://imazeo.com']);
        $this->assertSame('image/png', $result->contentType);
        $this->assertSame($png, $result->data);
        $this->assertSame('req_bin', $result->requestId);
    }

    public function testPassesImageListQFilter(): void
    {
        $handler = new MockHttpHandler();
        $handler->push(function (array $request): array {
            $this->assertStringContainsString('q=photo', $request['url']);

            return [
                'status' => 200,
                'headers' => ['content-type' => 'application/json', 'x-request-id' => 'req_test'],
                'body' => json_encode([
                    'success' => true,
                    'data' => [],
                    'meta' => ['requestId' => 'req_test'],
                ], JSON_THROW_ON_ERROR),
            ];
        });
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $client->images->list(['q' => 'photo', 'page' => 1]);
        $this->assertCount(1, $handler->requests);
    }

    public function testReturnsNotModifiedOnTransformDownload304(): void
    {
        $handler = new MockHttpHandler();
        $handler->push([
            'status' => 304,
            'headers' => [
                'etag' => '"abc"',
                'x-request-id' => 'req_304',
            ],
            'body' => '',
        ]);
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $result = $client->transforms->download('tr_1', [
            'ifNoneMatch' => '"abc"',
        ]);
        $this->assertTrue($result->notModified);
        $this->assertSame(0, strlen($result->data));
        $this->assertSame('req_304', $result->requestId);
        $this->assertSame('"abc"', $result->etag);
        $this->assertSame('"abc"', $handler->requests[0]['headers']['If-None-Match']);
    }

    public function testPostsTransformJsonAndReturnsBinary(): void
    {
        $handler = new MockHttpHandler();
        $handler->push(function (array $request): array {
            $this->assertSame('POST', $request['method']);
            $payload = json_decode((string) $request['body'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('img', $payload['imageId']);
            $this->assertSame(100, $payload['transform']['width']);
            $this->assertSame('webp', $payload['transform']['format']);

            return [
                'status' => 200,
                'headers' => [
                    'content-type' => 'image/webp',
                    'x-transformation-id' => 'tr_1',
                    'x-cache' => 'MISS',
                    'x-request-id' => 'req_t',
                ],
                'body' => "\x01\x02",
            ];
        });
        $client = new Imazeo([
            'apiKey' => 'k',
            'projectId' => 'p',
            'baseUrl' => 'https://api.example.com',
            'httpHandler' => $handler,
        ]);
        $out = $client->transforms->create([
            'imageId' => 'img',
            'transform' => ['width' => 100, 'format' => 'webp'],
        ]);
        $this->assertSame('tr_1', $out->transformationId);
        $this->assertSame('MISS', $out->cache);
    }
}
