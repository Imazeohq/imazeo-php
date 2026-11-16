<?php
declare(strict_types=1);
namespace Imazeo\Types;
final class ProcessingOptions
{
    /** @param array{idempotencyKey?: string, quoteId?: string} $options */
    public static function headers(array $options): array
    {
        $headers = [];
        if (isset($options['idempotencyKey'])) $headers['Idempotency-Key'] = $options['idempotencyKey'];
        if (isset($options['quoteId'])) $headers['X-Quote-ID'] = $options['quoteId'];
        return $headers;
    }
}
