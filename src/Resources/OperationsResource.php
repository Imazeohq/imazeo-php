<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class OperationsResource extends ProjectResource
{
    public function get(string $id): array { return $this->json('GET', ['operations', $id]); }
    public function lookup(array $input): array { return $this->json('POST', ['operations', 'lookup'], $input); }
    public function result(string $id): BinaryResult|array
    {
        $result = $this->http->requestBinary('GET', $this->url(['operations', $id, 'result']));
        return str_contains($result->contentType, 'application/json') ? json_decode($result->data, true, 512, JSON_THROW_ON_ERROR)['data'] : $result;
    }
}
