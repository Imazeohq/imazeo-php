<?php

declare(strict_types=1);

namespace Imazeo\Resources;

use Imazeo\Http\Multipart;
use Imazeo\Http\Transport;
use Imazeo\Types\BinaryResult;
use Imazeo\Version;

final class PdfResource
{
    /** @param callable(): string $projectId */
    public function __construct(
        private readonly Transport $http,
        private $projectId,
    ) {
    }

    /**
     * @param string|resource|array{data: string, filename?: string, contentType?: string} $source
     * @param array{filename?: string} $options
     * @return array{pageCount: int, encrypted: bool, pages: list<array<string, mixed>>}
     */
    public function info(mixed $source, array $options = []): array
    {
        $projectId = ($this->projectId)();
        $prepared = Multipart::prepareUpload($source, [
            'filename' => $options['filename'] ?? 'document.pdf',
            'contentType' => 'application/pdf',
        ]);
        $url = $this->http->path(['projects', $projectId, 'tools', 'pdf', 'info']);
        $res = $this->http->requestMultipartJson(
            'POST',
            $url,
            [['field' => 'file', 'upload' => $prepared]],
            timeoutMs: Version::PDF_TIMEOUT_MS,
        );

        /** @var array{pageCount: int, encrypted: bool, pages: list<array<string, mixed>>} */
        return $res['data'];
    }

    /**
     * @param string|resource|array{data: string, filename?: string, contentType?: string} $source
     * @param array{
     *   filename?: string,
     *   pages?: string,
     *   rangeStart?: int,
     *   rangeEnd?: int,
     *   all?: bool,
     *   format?: string,
     *   scale?: float|int,
     *   quality?: int
     * } $options
     * @return BinaryResult|array{pages: list<array<string, mixed>>, requestId: string}
     */
    public function render(mixed $source, array $options = []): BinaryResult|array
    {
        $projectId = ($this->projectId)();
        $prepared = Multipart::prepareUpload($source, [
            'filename' => $options['filename'] ?? 'document.pdf',
            'contentType' => 'application/pdf',
        ]);
        $query = [];
        if (isset($options['pages'])) {
            $query['pages'] = $options['pages'];
        }
        if (isset($options['rangeStart'])) {
            $query['rangeStart'] = (string) $options['rangeStart'];
        }
        if (isset($options['rangeEnd'])) {
            $query['rangeEnd'] = (string) $options['rangeEnd'];
        }
        if (isset($options['all'])) {
            $query['all'] = $options['all'] ? 'true' : 'false';
        }
        if (isset($options['format'])) {
            $query['format'] = $options['format'];
        }
        if (isset($options['scale'])) {
            $query['scale'] = (string) $options['scale'];
        }
        if (isset($options['quality'])) {
            $query['quality'] = (string) $options['quality'];
        }
        $url = $this->http->path(['projects', $projectId, 'tools', 'pdf', 'render']);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $result = $this->http->requestMultipartBinary(
            'POST',
            $url,
            [['field' => 'file', 'upload' => $prepared]],
            timeoutMs: Version::PDF_TIMEOUT_MS,
        );

        if ($result instanceof BinaryResult) {
            return $result;
        }

        $pages = [];
        if (is_array($result['data']) && isset($result['data']['pages']) && is_array($result['data']['pages'])) {
            $pages = $result['data']['pages'];
        }

        return [
            'pages' => $pages,
            'requestId' => $result['meta']['requestId'],
        ];
    }

    /**
     * @param list<string|resource|array{data: string, filename?: string, contentType?: string}> $sources
     */
    public function merge(array $sources): BinaryResult
    {
        if ($sources === []) {
            throw new \InvalidArgumentException('At least one PDF is required');
        }
        $projectId = ($this->projectId)();
        $files = [];
        foreach ($sources as $i => $source) {
            $prepared = Multipart::prepareUpload($source, [
                'filename' => 'file-' . ($i + 1) . '.pdf',
                'contentType' => 'application/pdf',
            ]);
            $files[] = ['field' => 'file', 'upload' => $prepared];
        }
        $url = $this->http->path(['projects', $projectId, 'tools', 'pdf', 'merge']);
        $result = $this->http->requestMultipartBinary(
            'POST',
            $url,
            $files,
            timeoutMs: Version::PDF_TIMEOUT_MS,
        );
        if (!$result instanceof BinaryResult) {
            throw new \RuntimeException('Expected PDF binary response from merge');
        }

        return $result;
    }

    /**
     * @param string|resource|array{data: string, filename?: string, contentType?: string} $source
     * @param array{
     *   filename?: string,
     *   pages?: string,
     *   rangeStart?: int,
     *   rangeEnd?: int
     * } $options
     */
    public function extract(mixed $source, array $options = []): BinaryResult
    {
        $projectId = ($this->projectId)();
        $prepared = Multipart::prepareUpload($source, [
            'filename' => $options['filename'] ?? 'document.pdf',
            'contentType' => 'application/pdf',
        ]);
        $query = [];
        if (isset($options['pages'])) {
            $query['pages'] = $options['pages'];
        }
        if (isset($options['rangeStart'])) {
            $query['rangeStart'] = (string) $options['rangeStart'];
        }
        if (isset($options['rangeEnd'])) {
            $query['rangeEnd'] = (string) $options['rangeEnd'];
        }
        $url = $this->http->path(['projects', $projectId, 'tools', 'pdf', 'extract']);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $result = $this->http->requestMultipartBinary(
            'POST',
            $url,
            [['field' => 'file', 'upload' => $prepared]],
            timeoutMs: Version::PDF_TIMEOUT_MS,
        );
        if (!$result instanceof BinaryResult) {
            throw new \RuntimeException('Expected PDF binary response from extract');
        }

        return $result;
    }
}
