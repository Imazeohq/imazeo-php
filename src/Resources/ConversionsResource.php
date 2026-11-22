<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class ConversionsResource extends ProjectResource
{
    public function capabilities(): array { return $this->json('GET', ['conversions', 'capabilities']); }
    public function list(string $engine = 'docx'): array { return $this->json('GET', ['conversions'], query: ['engine' => $engine]); }
    public function get(string $id): array { return $this->json('GET', ['conversions', $id]); }
    public function lookup(string $key): array { return $this->json('GET', ['conversions', 'lookup'], query: ['key' => $key]); }
    public function cancel(string $id): array { return $this->json('POST', ['conversions', $id, 'cancel']); }
    public function save(string $id): array { return $this->json('POST', ['conversions', $id, 'save']); }
    public function download(string $id): BinaryResult { return $this->http->requestBinary('GET', $this->url(['conversions', $id, 'download'])); }
    public function website(string $url, string $idempotencyKey, string $quoteId, array $options = []): array
    {
        return $this->json('POST', ['conversions', 'website'], ['url' => $url], $options, ['Idempotency-Key' => $idempotencyKey, 'X-Quote-ID' => $quoteId]);
    }
    private function upload(string $engine, mixed $source, string $idempotencyKey, string $quoteId, array $options): array
    {
        $upload = Multipart::prepareUpload($source, ['filename' => 'document.' . $engine]);
        $parts = $engine === 'docx' ? ['conversions'] : ['conversions', $engine];
        return $this->http->requestMultipartJson('POST', $this->url($parts, $options), [['field' => 'file', 'upload' => $upload]], $upload->byteLength, ['Idempotency-Key' => $idempotencyKey, 'X-Quote-ID' => $quoteId])['data'];
    }
    public function docx(mixed $source, string $idempotencyKey, string $quoteId, array $options = []): array { return $this->upload('docx', $source, $idempotencyKey, $quoteId, $options); }
    public function xlsx(mixed $source, string $idempotencyKey, string $quoteId, array $options = []): array { return $this->upload('xlsx', $source, $idempotencyKey, $quoteId, $options); }
    public function pptx(mixed $source, string $idempotencyKey, string $quoteId, array $options = []): array { return $this->upload('pptx', $source, $idempotencyKey, $quoteId, $options); }
}
