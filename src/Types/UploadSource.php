<?php

declare(strict_types=1);

namespace Imazeo\Types;

/**
 * Local file input for multipart uploads (never sent as a path string alone).
 *
 * Accepted forms:
 * - binary string
 * - array{data: string, filename?: string, contentType?: string}
 * - resource (stream)
 */
final class UploadSource
{
    private function __construct()
    {
    }
}
