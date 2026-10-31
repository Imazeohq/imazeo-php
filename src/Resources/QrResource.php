<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Transport;
use Imazeo\Types\BinaryResult;
use Imazeo\Version;

final class QrResource
{
    /** @param callable(): string $projectId */
    public function __construct(
        private readonly Transport $http,
        private $projectId,
    ) {
    }

    /**
     * @param array{
     *   content: string,
     *   format?: string,
     *   size?: int,
     *   margin?: int,
     *   errorCorrection?: string,
     *   foreground?: string,
     *   background?: string
     * } $input
     */
    public function generate(array $input): BinaryResult
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'tools', 'qr']);
        $body = json_encode($input, JSON_THROW_ON_ERROR);

        return $this->http->requestBinary(
            'POST',
            $url,
            $body,
            ['Content-Type' => 'application/json', 'Accept' => '*/*'],
            Version::LIGHT_TIMEOUT_MS,
        );
    }
}
