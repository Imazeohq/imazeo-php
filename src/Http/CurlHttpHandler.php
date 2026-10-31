<?php

declare(strict_types=1);

namespace Imazeo\Http;

use Imazeo\Errors\NetworkError;

/**
 * Default HTTP transport using PHP curl.
 */
final class CurlHttpHandler implements HttpHandlerInterface
{
    public function send(array $request): array
    {
        $ch = curl_init($request['url']);
        if ($ch === false) {
            throw new NetworkError('Failed to initialize curl');
        }

        $headers = [];
        foreach ($request['headers'] as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $timeoutMs = $request['timeoutMs'];
        $timeoutSec = max(1, (int) ceil($timeoutMs / 1000));

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $request['method'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT => min(30, $timeoutSec),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    if ($name !== '') {
                        if (isset($responseHeaders[$name])) {
                            $responseHeaders[$name] .= ', ' . $value;
                        } else {
                            $responseHeaders[$name] = $value;
                        }
                    }
                }

                return $len;
            },
        ]);

        if (array_key_exists('body', $request) && $request['body'] !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new NetworkError(
                    "Request timed out after {$timeoutMs}ms",
                );
            }
            throw new NetworkError($error !== '' ? $error : 'Network request failed');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
        ];
    }
}
