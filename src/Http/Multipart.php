<?php

declare(strict_types=1);

namespace Imazeo\Http;

final class Multipart
{
    /**
     * @param string|resource|array{data: string, filename?: string, contentType?: string} $source
     * @param array{filename?: string, contentType?: string} $defaults
     */
    public static function prepareUpload(mixed $source, array $defaults = []): PreparedUpload
    {
        if (is_string($source)) {
            return new PreparedUpload(
                $source,
                $defaults['filename'] ?? 'file.bin',
                $defaults['contentType'] ?? 'application/octet-stream',
                strlen($source),
            );
        }

        if (is_resource($source)) {
            $bytes = stream_get_contents($source);
            if ($bytes === false) {
                throw new \InvalidArgumentException('Failed to read upload stream');
            }

            return new PreparedUpload(
                $bytes,
                $defaults['filename'] ?? 'file.bin',
                $defaults['contentType'] ?? 'application/octet-stream',
                strlen($bytes),
            );
        }

        if (is_array($source) && isset($source['data']) && is_string($source['data'])) {
            return new PreparedUpload(
                $source['data'],
                $source['filename'] ?? $defaults['filename'] ?? 'file.bin',
                $source['contentType'] ?? $defaults['contentType'] ?? 'application/octet-stream',
                strlen($source['data']),
            );
        }

        throw new \InvalidArgumentException('Unsupported upload source');
    }

    /**
     * Build a multipart/form-data body with one or more file parts.
     *
     * @param list<array{field: string, upload: PreparedUpload}> $files
     * @return array{body: string, contentType: string}
     */
    public static function build(array $files): array
    {
        $boundary = '----ImazeoBoundary' . bin2hex(random_bytes(16));
        $body = '';

        foreach ($files as $file) {
            $field = $file['field'];
            $upload = $file['upload'];
            $filename = self::escapeFilename($upload->filename);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$field}\"; filename=\"{$filename}\"\r\n";
            $body .= 'Content-Type: ' . $upload->contentType . "\r\n\r\n";
            $body .= $upload->bytes . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return [
            'body' => $body,
            'contentType' => "multipart/form-data; boundary={$boundary}",
        ];
    }

    private static function escapeFilename(string $filename): string
    {
        return str_replace(['\\', '"'], ['_', '_'], $filename);
    }
}
