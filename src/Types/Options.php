<?php

declare(strict_types=1);

namespace Imazeo\Types;

/**
 * Client construction options.
 *
 * @phpstan-type ImazeoOptionsArray array{
 *   apiKey: string,
 *   projectId?: string,
 *   baseUrl?: string,
 *   apiVersion?: string,
 *   timeoutMs?: int,
 *   appName?: string,
 *   httpHandler?: \Imazeo\Http\HttpHandlerInterface
 * }
 */
final class Options
{
    /**
     * @param array{
     *   apiKey: string,
     *   projectId?: string,
     *   baseUrl?: string,
     *   apiVersion?: string,
     *   timeoutMs?: int,
     *   appName?: string,
     *   httpHandler?: \Imazeo\Http\HttpHandlerInterface
     * } $options
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly ?string $projectId = null,
        public readonly ?string $baseUrl = null,
        public readonly ?string $apiVersion = null,
        public readonly ?int $timeoutMs = null,
        public readonly ?string $appName = null,
        public readonly ?\Imazeo\Http\HttpHandlerInterface $httpHandler = null,
    ) {
    }

    /**
     * @param array{
     *   apiKey: string,
     *   projectId?: string,
     *   baseUrl?: string,
     *   apiVersion?: string,
     *   timeoutMs?: int,
     *   appName?: string,
     *   httpHandler?: \Imazeo\Http\HttpHandlerInterface
     * } $options
     */
    public static function fromArray(array $options): self
    {
        if (!isset($options['apiKey']) || !is_string($options['apiKey'])) {
            throw new \InvalidArgumentException('apiKey is required');
        }

        return new self(
            apiKey: $options['apiKey'],
            projectId: isset($options['projectId']) ? (string) $options['projectId'] : null,
            baseUrl: isset($options['baseUrl']) ? (string) $options['baseUrl'] : null,
            apiVersion: isset($options['apiVersion']) ? (string) $options['apiVersion'] : null,
            timeoutMs: isset($options['timeoutMs']) ? (int) $options['timeoutMs'] : null,
            appName: isset($options['appName']) ? (string) $options['appName'] : null,
            httpHandler: $options['httpHandler'] ?? null,
        );
    }
}
