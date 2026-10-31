<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Transport;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;

final class TransformsResource
{
    /** @param callable(): string $projectId */
    public function __construct(
        private readonly Transport $http,
        private $projectId,
    ) {
    }

    /**
     * @param array{
     *   imageId?: string,
     *   url?: string,
     *   mode?: string,
     *   maxOutputBytes?: int,
     *   transform?: array<string, mixed>
     * } $request
     */
    public function create(array $request): BinaryResult
    {
        $hasImageId = isset($request['imageId']) && $request['imageId'] !== '';
        $hasUrl = isset($request['url']) && $request['url'] !== '';
        if (!$hasImageId && !$hasUrl) {
            throw new \InvalidArgumentException('Exactly one of imageId or url is required');
        }
        if ($hasImageId && $hasUrl) {
            throw new \InvalidArgumentException('Provide only one of imageId or url');
        }

        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'transforms']);
        $body = json_encode($request, JSON_THROW_ON_ERROR);

        return $this->http->requestBinary(
            'POST',
            $url,
            $body,
            ['Content-Type' => 'application/json', 'Accept' => '*/*'],
        );
    }

    /**
     * @param array{page?: int, limit?: int} $params
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
        $url = $this->http->path(['projects', $projectId, 'transforms']);
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
        $url = $this->http->path(['projects', $projectId, 'transforms', $id]);
        $res = $this->http->requestJson('GET', $url);

        /** @var array<string, mixed> */
        return $res['data'];
    }

    /**
     * @param array{ifNoneMatch?: string} $options
     */
    public function download(string $id, array $options = []): BinaryResult
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'transforms', $id, 'content']);
        $headers = [];
        if (isset($options['ifNoneMatch']) && $options['ifNoneMatch'] !== '') {
            $headers['If-None-Match'] = $options['ifNoneMatch'];
        }

        return $this->http->requestBinary('GET', $url, null, $headers);
    }

    /** @return array{message: string} */
    public function delete(string $id): array
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'transforms', $id]);
        $res = $this->http->requestJson('DELETE', $url);

        /** @var array{message: string} */
        return $res['data'];
    }
}
