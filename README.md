# imazeo/sdk

Official **server-side** PHP SDK for Imazeo.

|                |                                       |
| -------------- | ------------------------------------- |
| Package        | `imazeo/sdk`                          |
| SDK version    | `1.0.0`                               |
| Default API    | `v1`                                  |
| Supported APIs | `v1`                                  |
| Runtime        | PHP **8.2+** (`ext-curl`, `ext-json`) |
| Registry       | [Packagist](https://packagist.org/packages/imazeo/sdk) |
| Source         | [GitHub](https://github.com/Imazeohq/imazeo-php) |

> **Security:** Secret API keys (`X-API-Key`) are for trusted servers only.
> Do **not** ship this client with a secret key in browsers or front-end code.

Integration contract and learning path: [GUIDE.md](GUIDE.md).

## Install

```bash
composer require imazeo/sdk
```

Requires PHP 8.2+ with `ext-curl` and `ext-json`. Create a project API key in the
[Imazeo Customer portal](https://app.imazeo.com). Default API origin is
`https://api.imazeo.com` (override with `baseUrl` when needed).

## Learning path

Read the self-contained [integration guide](GUIDE.md) for project/key setup,
language examples, PDF response types, pagination, output retention, troubleshooting
and production practices.

The default curl transport refuses redirects (`CURLOPT_FOLLOWLOCATION` off) so
credentials stay on the configured API origin.

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Imazeo\Imazeo;

$client = new Imazeo([
    'apiKey' => getenv('IMAZEO_API_KEY'),
    'baseUrl' => getenv('IMAZEO_API_ORIGIN'), // supplied API origin
    // 'projectId' => '…', // optional; otherwise resolved via /v1/api-key/context
]);

$qr = $client->qr->generate([
    'content' => 'https://imazeo.com',
    'format' => 'png',
    'size' => 256,
]);
file_put_contents('qr.png', $qr->data);
```

## Authentication

```php
new Imazeo(['apiKey' => getenv('IMAZEO_API_KEY')]);
```

Sends `X-API-Key` only. Never send `Authorization` together with an API key
(backend rejects that combination).

## Resources

| Resource              | Methods                                                                       |
| --------------------- | ----------------------------------------------------------------------------- |
| `$client->context`    | `get()` → project/key environment                                             |
| `$client->images`     | `upload`, `list` (`page`/`limit`/`q`), `get`, `delete`, `download`            |
| `$client->transforms` | `create`, `list`, `get`, `download` (`ifNoneMatch` → `notModified`), `delete` |
| `$client->qr`         | `generate`                                                                    |
| `$client->barcodes`   | `generate`                                                                    |
| `$client->pdf`        | `info`, `render`, `merge`, `extract`                                          |

### Upload + transform

```php
$image = $client->images->upload($bytes, [
    'filename' => 'photo.jpg',
    'contentType' => 'image/jpeg',
]);

$out = $client->transforms->create([
    'imageId' => $image['_id'],
    'mode' => 'EPHEMERAL',
    'transform' => ['width' => 1200, 'format' => 'webp', 'quality' => 85],
]);
// $out->data → binary string
```

### Remote URL transform

```php
$out = $client->transforms->create([
    'url' => 'https://example.com/image.jpg',
    'transform' => ['width' => 640, 'format' => 'jpeg'],
]);
```

### PDF

```php
$info = $client->pdf->info($pdfBytes);
$page = $client->pdf->render($pdfBytes, ['pages' => '1', 'format' => 'png']);
$merged = $client->pdf->merge([$pdfA, $pdfB]);
$extracted = $client->pdf->extract($pdfBytes, ['pages' => '1,2']);
```

## Errors

```php
use Imazeo\Errors\ImazeoError;
use Imazeo\Errors\RateLimitError;

try {
    $client->images->get($id);
} catch (RateLimitError $error) {
    error_log($error->errorCode . ' ' . $error->requestId);
} catch (ImazeoError $error) {
    error_log($error->status . ' ' . $error->errorCode . ' ' . $error->getMessage());
}
```

## Configuration

| Option        | Default                  | Notes                                  |
| ------------- | ------------------------ | -------------------------------------- |
| `apiKey`      | required                 | Secret key                             |
| `projectId`   | from context             | Path project                           |
| `baseUrl`     | `https://api.imazeo.com` | Origin only; `/v1` stripped if present |
| `apiVersion`  | `v1`                     | Allowlisted                            |
| `timeoutMs`   | `60000`                  | PDF uses 120s; QR/barcode/context 30s  |
| `appName`     | —                        | Appended to User-Agent                 |
| `httpHandler` | curl                     | Tests / custom transport               |

User-Agent: `Imazeo-PHP/1.0.0`.

## API version vs package version

- **API** path version: `v1` (configured via `apiVersion`)
- **SDK** SemVer: `composer.json` → `1.0.0`

`Imazeo::apiCompatibility()` reports supported API versions.

## Not included

Portal JWT APIs (projects CRUD, API-key management, usage, plans, tickets, auth),
admin APIs, tool artifacts, project outputs, and browser secret-key usage.

## Development

```bash
composer install
composer test
```

## License

MIT

## Barcode and PDF notes

Barcode generation requires `type` and string `content`. Optional `format` is
`png` or `svg` (default `png`); `includeText` defaults to `true`.
PDF merge uploads files in order as repeated multipart `file` parts.
Omit optional fields to use API defaults. Integer fields such as barcode scale
and image hue must be integers.

```php
$client->barcodes->generate(['type' => 'code128', 'content' => 'IMAZEO-2026', 'format' => 'svg', 'includeText' => false]);
```
