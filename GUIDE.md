# Imazeo PHP integration guide

## 1. Prepare a server integration

Install the package:

```bash
composer require imazeo/sdk
```

Package: [`imazeo/sdk`](https://packagist.org/packages/imazeo/sdk) on Packagist.
Source: [Imazeohq/imazeo-php](https://github.com/Imazeohq/imazeo-php).
Use the package README for runtime details.

Create a Customer account, complete required verification, create a project and
issue a project key. Set IMAZEO_API_KEY in protected server configuration and
IMAZEO_API_ORIGIN to the supplied API origin (no /v1 suffix; production default
`https://api.imazeo.com`). Use HTTPS outside loopback development. A key can
resolve its project through GET /v1/api-key/context; that response has projectId
and keyId, not plan limits or the secret key.

The Main site's documentation paths, served by your configured Main origin, are:
/docs/getting-started/, /docs/authentication/ and /docs/projects/.
These paths are site-relative; this guide does not assume a live production domain.

## 2. Generate a QR code and render a delivery note

The following example uses `imazeo/sdk`. Obtain the original two-page synthetic
delivery-note.pdf from the Main site's /examples/assets/delivery-note.pdf and put it
in the working directory. The event URL is fictional. Run only against your configured
API deployment with the required entitlements and credits.

```php
<?php
require 'vendor/autoload.php';
use Imazeo\Imazeo;
use Imazeo\Types\BinaryResult;

$key = getenv('IMAZEO_API_KEY');
$origin = getenv('IMAZEO_API_ORIGIN');
if (!$key || !$origin) throw new RuntimeException('Set key and API origin');
$client = new Imazeo(['apiKey' => $key, 'baseUrl' => $origin]);
$write = static function (string $path, string $bytes): void {
    if (file_put_contents($path, $bytes) === false) throw new RuntimeException('Write failed');
};
$qr = $client->qr->generate([
    'content' => 'https://example.com/events/studio-open-day', 'format' => 'svg', 'size' => 384,
]);
$write('event-check-in.svg', $qr->data);
$pdf = file_get_contents('delivery-note.pdf');
if ($pdf === false) throw new RuntimeException('PDF read failed');
$result = $client->pdf->render($pdf, ['pages' => '1,2', 'format' => 'png']);
if ($result instanceof BinaryResult) {
    $write('delivery-page.png', $result->data);
} else {
    foreach ($result['pages'] as $page) {
        $bytes = base64_decode($page['data'], true);
        if ($bytes === false) throw new RuntimeException('Invalid base64 page');
        $write('delivery-page-' . $page['pageNumber'] . '.png', $bytes);
    }
}
```

All six SDK default transports refuse HTTP redirects so project credentials stay
on the configured API origin. The examples also set safe redirect policies where
custom client hooks are used. Custom fetch functions, Java HttpClient instances,
.NET HttpClient/message handlers and Python/PHP handlers must preserve this rule.
Never forward project credentials to a redirected host.

QR success is binary SVG. A one-page PDF render returns binary image bytes; multiple
selected pages return base64 page data. This SDK's render result is:
BinaryResult or array with pages and requestId. Decode base64 exactly once; use each page's contentType if you
change the selected format. The example uses PNG and .png filenames deliberately.

## 3. Upload and transform images

Use the image upload method with file bytes and explicit filename/content type
as documented in the README. It sends multipart field file and the exact
X-Upload-Length file byte count. Filenames are metadata, not paths or resource IDs.
Keep the returned image ID, then create a transform with imageId and transform.
REST uses _id; use the native ID accessor shown in the README.

CACHE and PERSIST retain derivatives according to their mode rules. EPHEMERAL
only returns immediate bytes. All transform success bodies are binary. Image
originals preserve original metadata; generated derivatives strip metadata.
The HTTP guides are /docs/images/, /docs/transforms/ and /docs/quickstart/.

## 4. Choose PDF and code inputs

PDF info returns parsed JSON and costs one credit. Render costs one per selected
page; merge costs two; extract costs one. Page numbers are one-based. Select
pages, a complete rangeStart/rangeEnd pair, or render-only all=true; do not combine
selectors. Render defaults to page 1; extract requires an explicit selection.
Merge upload order determines document order. Encrypted/invalid PDFs are rejected.

QR and barcode generation each cost one credit and return PNG or SVG. Barcode
content must be a string, including numeric identifiers with leading zeros.
Send numbers and booleans as native values, and omit unused optional fields instead
of null. A barcode renderer does not assign registered retail identifiers; QR
generation does not provide dynamic redirect hosting.
Detailed guides: /docs/pdf/, /docs/qr/, /docs/barcodes/ and /docs/workflows/.

## 5. Handle pagination, downloads and errors

Image/transform lists return a Page wrapper with items and page/limit/total/pages.
Pagination is explicit; clients do not silently fetch all pages. Only image lists
accept q as a filename-prefix filter; transformation lists accept page and limit.
This is not unrestricted full-text search.
Binary results contain bytes, content type, headers and request ID. Derivative
downloads support conditional ETags. A not-modified result has no new bytes:
retain your cached file instead of replacing it with an empty body.

SDK errors expose status, code, request ID and details using native language names
listed in the README. Network errors do not guarantee an HTTP status. Log only
sanitized diagnostics; never log a key, source media or base64 PDF pages. Fix
validation/authentication/entitlement failures. For temporary 429 or busy responses,
use bounded backoff only when replay is safe and honor Retry-After if supplied.
No general idempotency-key mechanism is promised. A timed-out mutation may already
have completed; reconcile known IDs/state before repeating it. SDKs do not
automatically retry mutations.
See /docs/errors/ and /docs/troubleshooting/.

## 6. Retain outputs and operate in production

PDF/code output is transient unless you retain the bytes or separately save a
private artifact through HTTP. Saved artifacts, unified output inventory, usage,
account/project/key management and Admin APIs are outside the SDK's core media
scope. Use Customer Bearer authentication for management, not a project key.
Do not combine Bearer and X-API-Key in one request.

There is no public CDN or customer signed-delivery URL in this contract. Deliver
bytes through your own authorized server. Never bundle a project key into browser,
mobile or end-user applications. Your server must enforce its own per-user access.

Bound source sizes, output bytes, page counts and concurrency: these SDKs buffer
media and base64 pages require extra memory. Default client timeouts are 30 seconds
for light operations, 60 seconds for images and 120 seconds for PDFs; they do not
extend backend worker deadlines. Check assigned account limits, not just public
pricing templates. Cache hits still consume output bandwidth; an authenticated
derivative 304 consumes no output bandwidth. Deletion refunds storage, not past
credits/bandwidth. Expiry and physical cleanup are separate.

Production and reference paths: /docs/outputs/, /docs/limits/, /docs/security/,
/docs/production/ and /docs/api-reference/. The developer OpenAPI download is
/openapi/imazeo-developer.json. It is a curated public API snapshot and excludes
admin/account operations and the unified Outputs list endpoint; use
resource-specific lists to enumerate inventory.
