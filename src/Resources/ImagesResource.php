<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Multipart;
use Imazeo\Http\Transport;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;

final class ImagesResource
{
    /** @param callable(): string $projectId */
    public function __construct(
        private readonly Transport $http,
        private $projectId,
    ) {
    }

    /**
     * @param string|resource|array{data: string, filename?: string, contentType?: string} $source
     * @param array{filename?: string, contentType?: string} $options
     * @return array<string, mixed>
     */
    public function upload(mixed $source, array $options = []): array
    {
        $projectId = ($this->projectId)();
        $defaults = [];
        if (isset($options['filename'])) {
            $defaults['filename'] = $options['filename'];
        }
        if (isset($options['contentType'])) {
            $defaults['contentType'] = $options['contentType'];
        }
        $prepared = Multipart::prepareUpload($source, $defaults);

        $url = $this->http->path(['projects', $projectId, 'images']);
        $res = $this->http->requestMultipartJson(
            'POST',
            $url,
            [['field' => 'file', 'upload' => $prepared]],
            uploadLength: $prepared->byteLength,
        );

        /** @var array<string, mixed> */
        return $res['data'];
    }

    /**
     * @param array{page?: int, limit?: int, q?: string} $params
     * @return Page<array<string, mixed>>
     */
    public function list(array $params = []): Page
    {
        $projectId = ($this->projectId)();
        $query = [];
        if (isset($params['page'])) {
            $query['page'] = (string) $params['page'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = (string) $params['limit'];
        }
        if (isset($params['q']) && $params['q'] !== '') {
            $query['q'] = $params['q'];
        }
        $url = $this->http->path(['projects', $projectId, 'images']);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $res = $this->http->requestJson('GET', $url);
        $items = is_array($res['data']) ? $res['data'] : [];

        return new Page(
            items: $items,
            page: $res['meta']['page'] ?? $params['page'] ?? 1,
            limit: $res['meta']['limit'] ?? $params['limit'] ?? 20,
            total: $res['meta']['total'] ?? count($items),
            pages: $res['meta']['pages'] ?? 1,
            requestId: $res['meta']['requestId'],
        );
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'images', $id]);
        $res = $this->http->requestJson('GET', $url);

        /** @var array<string, mixed> */
        return $res['data'];
    }

    /** @return array{message: string} */
    public function delete(string $id): array
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'images', $id]);
        $res = $this->http->requestJson('DELETE', $url);

        /** @var array{message: string} */
        return $res['data'];
    }

    public function download(string $id): BinaryResult
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'images', $id, 'content']);

        return $this->http->requestBinary('GET', $url);
    }
}
