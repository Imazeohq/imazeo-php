<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class DynamicCodesResource extends ProjectResource
{
    public function capabilities(): array { return $this->json('GET', ['dynamic-codes', 'capabilities']); }
    public function list(array $params = []): Page { return $this->page(['dynamic-codes'], $params); }
    public function get(string $id): array { return $this->json('GET', ['dynamic-codes', $id]); }
    public function create(array $input, string $idempotencyKey): array { return $this->json('POST', ['dynamic-codes'], $input, headers: ['Idempotency-Key' => $idempotencyKey]); }
    public function update(string $id, array $input, string $idempotencyKey): array { return $this->json('PATCH', ['dynamic-codes', $id], $input, headers: ['Idempotency-Key' => $idempotencyKey]); }
    public function analytics(string $id, array $query = []): array { return $this->json('GET', ['dynamic-codes', $id, 'analytics'], query: $query); }
    public function exportAnalytics(string $id, array $query = []): BinaryResult { return $this->http->requestBinary('GET', $this->url(['dynamic-codes', $id, 'analytics', 'export'], $query)); }
}
