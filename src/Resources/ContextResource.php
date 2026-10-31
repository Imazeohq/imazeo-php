<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Transport;
use Imazeo\Version;

final class ContextResource
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * @return array{projectId: string, keyId: string}
     */
    public function get(): array
    {
        $url = $this->http->path(['api-key', 'context']);
        $res = $this->http->requestJson('GET', $url, timeoutMs: Version::LIGHT_TIMEOUT_MS);

        /** @var array{projectId: string, keyId: string} */
        return $res['data'];
    }
}
