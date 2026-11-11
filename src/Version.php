<?php

declare(strict_types=1);

namespace Imazeo;

/** Derived from composer.json; keep in sync with package version. */
final class Version
{
    public const SDK_VERSION = '1.0.3';

    public const DEFAULT_API_VERSION = 'v1';

    /** @var list<string> */
    public const SUPPORTED_API_VERSIONS = ['v1'];

    public const DEFAULT_BASE_URL = 'https://api.imazeo.com';

    /** Default request timeout (image upload / transform / download). */
    public const DEFAULT_TIMEOUT_MS = 60_000;

    /** PDF operations. */
    public const PDF_TIMEOUT_MS = 120_000;

    /** JSON / lightweight (QR, barcode, context). */
    public const LIGHT_TIMEOUT_MS = 30_000;
}
