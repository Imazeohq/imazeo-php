<?php

declare(strict_types=1);

namespace Imazeo\Http;

/**
 * Low-level HTTP handler. Inject a mock in tests.
 */
interface HttpHandlerInterface
{
    /**
     * @param array{
     *   method: string,
     *   url: string,
     *   headers: array<string, string>,
     *   body?: string|null,
     *   timeoutMs: int
     * } $request
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function send(array $request): array;
}
