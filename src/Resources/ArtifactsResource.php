<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class ArtifactsResource extends ProjectResource
{
    public function list(array $params = []): Page { return $this->page(['tools', 'artifacts'], $params); }
    public function get(string $id): array { return $this->json('GET', ['tools', 'artifacts', $id]); }
    public function delete(string $id): array { return $this->json('DELETE', ['tools', 'artifacts', $id]); }
    public function download(string $id): BinaryResult { return $this->http->requestBinary('GET', $this->url(['tools', 'artifacts', $id, 'content'])); }
    public function save(mixed $source, array $input): array
    {
        $upload = Multipart::prepareUpload($source, ['contentType' => $input['contentType']]);
        if (isset($input['requestSnapshot'])) $input['requestSnapshot'] = json_encode($input['requestSnapshot'], JSON_THROW_ON_ERROR);
        return $this->http->requestMultipartJson('POST', $this->url(['tools', 'artifacts']), [['field' => 'file', 'upload' => $upload]], $upload->byteLength, fields: $input)['data'];
    }
}
