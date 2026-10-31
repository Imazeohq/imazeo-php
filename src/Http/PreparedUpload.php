<?php

declare(strict_types=1);

namespace Imazeo\Http;

/**
 * Prepared multipart file part.
 */
final class PreparedUpload
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $filename,
        public readonly string $contentType,
        public readonly int $byteLength,
    ) {
    }
}
