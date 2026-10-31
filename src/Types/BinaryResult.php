<?php

declare(strict_types=1);

namespace Imazeo\Types;

/**
 * Binary API response (image/PDF/QR/barcode content).
 */
final class BinaryResult
{
    /**
     * @param array<string, string> $headers Lowercased header map
     */
    public function __construct(
        public readonly string $data,
        public readonly string $contentType,
        public readonly string $requestId,
        public readonly array $headers,
        public readonly ?string $etag = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $format = null,
        public readonly ?string $transformationId = null,
        public readonly ?string $cache = null,
        public readonly ?int $pdfPage = null,
        /** True when the server returned HTTP 304 for an If-None-Match request. */
        public readonly bool $notModified = false,
    ) {
    }
}
