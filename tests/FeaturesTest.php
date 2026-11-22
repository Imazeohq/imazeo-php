<?php
declare(strict_types=1);

namespace Imazeo\Tests;

use Imazeo\Http\HttpHandlerInterface;
use Imazeo\Imazeo;
use PHPUnit\Framework\TestCase;

final class FeatureHandler implements HttpHandlerInterface
{
    public array $requests = [];
    public function send(array $request): array
    {
        $this->requests[] = $request;
        return [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['success' => true, 'data' => ['id' => 'record'], 'meta' => ['requestId' => 'request']], JSON_THROW_ON_ERROR),
        ];
    }
}

final class FeaturesTest extends TestCase
{
    private function client(FeatureHandler $handler): Imazeo
    {
        return new Imazeo(['apiKey' => 'test-key', 'projectId' => 'project', 'httpHandler' => $handler]);
    }

    public function testConversionHeadersAndFalseQuery(): void
    {
        $handler = new FeatureHandler();
        $client = $this->client($handler);
        $client->conversions->xlsx('abc', 'operation-key', 'quote-id', ['showGridLines' => false]);
        $request = $handler->requests[0];
        self::assertStringEndsWith('/conversions/xlsx?showGridLines=false', $request['url']);
        self::assertSame('operation-key', $request['headers']['Idempotency-Key']);
        self::assertSame('quote-id', $request['headers']['X-Quote-ID']);
        self::assertSame('3', $request['headers']['X-Upload-Length']);
        self::assertStringContainsString('abc', $request['body']);
        self::assertSame('test-key', $request['headers']['X-API-Key']);
        self::assertArrayNotHasKey('Authorization', $request['headers']);
    }

    public function testDynamicEditRevision(): void
    {
        $handler = new FeatureHandler();
        $this->client($handler)->dynamicCodes->update('code', ['expectedRevision' => 0, 'analyticsEnabled' => false], 'edit-key');
        $request = $handler->requests[0];
        self::assertSame('PATCH', $request['method']);
        self::assertSame(['expectedRevision' => 0, 'analyticsEnabled' => false], json_decode($request['body'], true));
        self::assertSame('edit-key', $request['headers']['Idempotency-Key']);
    }

    public function testArtifactMultipartTextFields(): void
    {
        $handler = new FeatureHandler();
        $this->client($handler)->artifacts->save('abc', ['kind' => 'qr', 'displayName' => 'Company QR', 'contentType' => 'image/png', 'requestSnapshot' => ['format' => 'png']]);
        $body = $handler->requests[0]['body'];
        self::assertStringContainsString('name="kind"' . "\r\n\r\nqr", $body);
        self::assertStringNotContainsString('name="kind"; filename=', $body);
        self::assertStringContainsString('{"format":"png"}', $body);
    }
}
