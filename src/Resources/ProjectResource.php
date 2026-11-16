<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Transport;
use Imazeo\Types\Page;
class ProjectResource
{
    public function __construct(protected readonly Transport $http, protected $projectId) {}
    protected function url(array $parts, array $query = []): string
    {
        $url = $this->http->path(['projects', ($this->projectId)(), ...$parts]);
        $query = array_filter($query, static fn ($v) => $v !== null);
        $query = array_map(static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $query);
        return $url . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }
    protected function json(string $method, array $parts, mixed $body = null, array $query = [], array $headers = []): mixed
    {
        return $this->http->requestJson($method, $this->url($parts, $query), $body, $headers)['data'];
    }
    protected function page(array $parts, array $query): Page
    {
        $result = $this->http->requestJson('GET', $this->url($parts, $query));
        $meta = $result['meta']; $data = $result['data'];
        return new Page($data, $meta['page'] ?? 1, $meta['limit'] ?? 20, $meta['total'] ?? count($data), $meta['pages'] ?? 1, $meta['requestId']);
    }
}
