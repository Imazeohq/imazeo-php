<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Transport;
use Imazeo\Types\BinaryResult;
use Imazeo\Version;

final class BarcodesResource
{
    /** @param callable(): string $projectId */
    public function __construct(
        private readonly Transport $http,
        private $projectId,
    ) {
    }

    /**
     * @param array{
     *   type: 'code128'|'code39'|'ean13'|'ean8'|'upca'|'itf14',
     *   content: string,
     *   format?: 'png'|'svg',
     *   scale?: int,
     *   height?: int,
     *   includeText?: bool,
     *   foreground?: string,
     *   background?: string
     * } $input
     */
    public function generate(array $input): BinaryResult
    {
        $projectId = ($this->projectId)();
        $url = $this->http->path(['projects', $projectId, 'tools', 'barcode']);
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
