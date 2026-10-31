<?php

declare(strict_types=1);

namespace Imazeo;

use Imazeo\Http\CurlHttpHandler;
use Imazeo\Http\HttpHandlerInterface;
use Imazeo\Http\Transport;
use Imazeo\Resources\BarcodesResource;
use Imazeo\Resources\ContextResource;
use Imazeo\Resources\ImagesResource;
use Imazeo\Resources\PdfResource;
use Imazeo\Resources\QrResource;
use Imazeo\Resources\TransformsResource;
use Imazeo\Types\Options;

/**
 * Official Imazeo server-side client.
 *
 * Uses secret `X-API-Key` authentication. Do not embed API keys in browsers.
 */
final class Imazeo
{
    public readonly ContextResource $context;

    public readonly ImagesResource $images;

    public readonly TransformsResource $transforms;

    public readonly QrResource $qr;

    public readonly BarcodesResource $barcodes;

    public readonly PdfResource $pdf;

    private readonly Transport $http;

    private readonly ?string $configuredProjectId;

    private ?string $resolvedProjectId = null;

    /**
     * @param array{
     *   apiKey: string,
     *   projectId?: string,
     *   baseUrl?: string,
     *   apiVersion?: string,
     *   timeoutMs?: int,
     *   appName?: string,
     *   httpHandler?: HttpHandlerInterface
     * }|Options $options
     */
    public function __construct(array|Options $options)
    {
        $opts = $options instanceof Options ? $options : Options::fromArray($options);

        $config = Transport::createConfig([
            'apiKey' => $opts->apiKey,
            'baseUrl' => $opts->baseUrl,
            'apiVersion' => $opts->apiVersion,
            'timeoutMs' => $opts->timeoutMs,
            'appName' => $opts->appName,
        ]);

        $handler = $opts->httpHandler ?? new CurlHttpHandler();
        $this->http = new Transport($config, $handler);

        $trimmed = $opts->projectId !== null ? trim($opts->projectId) : '';
        $this->configuredProjectId = $trimmed !== '' ? $trimmed : null;

        $resolveProjectId = fn (): string => $this->resolveProjectId();

        $this->context = new ContextResource($this->http);
        $this->images = new ImagesResource($this->http, $resolveProjectId);
        $this->transforms = new TransformsResource($this->http, $resolveProjectId);
        $this->qr = new QrResource($this->http, $resolveProjectId);
        $this->barcodes = new BarcodesResource($this->http, $resolveProjectId);
        $this->pdf = new PdfResource($this->http, $resolveProjectId);
    }

    /** SDK package version (SemVer). */
    public static function version(): string
    {
        return Version::SDK_VERSION;
    }

    /**
     * Default and supported API versions.
     *
     * @return array{defaultApi: string, supported: list<string>}
     */
    public static function apiCompatibility(): array
    {
        return [
            'defaultApi' => Version::DEFAULT_API_VERSION,
            'supported' => Version::SUPPORTED_API_VERSIONS,
        ];
    }

    private function resolveProjectId(): string
    {
        if ($this->configuredProjectId !== null) {
            return $this->configuredProjectId;
        }
        if ($this->resolvedProjectId !== null) {
            return $this->resolvedProjectId;
        }
        $ctx = $this->context->get();
        $this->resolvedProjectId = $ctx['projectId'];

        return $this->resolvedProjectId;
    }
}
