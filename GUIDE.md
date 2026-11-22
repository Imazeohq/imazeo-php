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

The default transport refuses HTTP redirects so project credentials stay on the
configured API origin. Custom transport hooks must preserve that rule.
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
REST uses \_id; use the native ID accessor shown in the README.

CACHE and PERSIST retain derivatives according to their mode rules. EPHEMERAL
only returns immediate bytes. All transform success bodies are binary. Image
originals preserve original metadata; generated derivatives strip metadata.
The HTTP guides are /docs/images/, /docs/transforms/ and /docs/quickstart/.

## 4. Choose PDF and code inputs

PDF info returns parsed JSON. Obtain processing prices from a credit quote. Page numbers are one-based. Select
pages, a complete rangeStart/rangeEnd pair, or render-only all=true; do not combine
selectors. Render defaults to page 1; extract requires an explicit selection.
Merge upload order determines document order. Encrypted/invalid PDFs are rejected.

QR and barcode generation return PNG or SVG; quote their processing credits. Barcode
content must be a string, including numeric identifiers with leading zeros.
Send numbers and booleans as native values, and omit unused optional fields instead
of null. A barcode renderer does not assign registered retail identifiers; QR
generation encodes static content. Use the dynamic QR resource for managed redirect links.
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
A timed-out mutation may already have completed. Use operation or conversion
lookup with its original key before repeating it. Inspect quote replay support
and operation state; the SDK does not automatically retry mutations.
See /docs/errors/ and /docs/troubleshooting/.

## 6. Retain outputs and operate in production

PDF/code output is transient unless you retain the bytes or save a private
artifact. The artifacts and outputs resources support storage and inventory.
Account/project/key management, billing and Admin APIs remain outside the SDK.
Use Customer Bearer authentication for account management, not a project key.
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
/openapi/imazeo-developer.json. The SDK also ships an API-key OpenAPI snapshot in
`openapi/backend.json`, covering its project resources and excluding admin/account
operations.

## Processing, conversions and dynamic QR

Get a credit quote before creating a conversion. Quote kinds are `docx.render`,
`xlsx.render`, `pptx.render` and `website.capture`, each with `units: 1` for a
single selected page, sheet, slide or website image. Conversion creation requires
an owned, unexpired quote and a UUID v4 idempotency key. Rendering options go in
the query; website URLs go in the JSON body. Check conversion capabilities for
enabled engines, allowed formats and limits.

Creation returns a job with status `queued`, `running`, `cancelling`, `completed`,
`failed` or `cancelled`. Keep its ID and key. Check status at bounded intervals;
download or save only a completed job. `lookup` recovers a job by its original
key. Downloaded results expire; saving creates a private artifact. Cancellation
and failures are terminal outcomes to inspect before submitting more work.

For synchronous media operations, request quotes with `code.qr`, `code.barcode`,
`pdf.info`, `pdf.render`, `pdf.merge`, `pdf.extract` or `image.transform`.
Pass `remote: true` when quoting a transform from a public URL. Prices come from
the quote. Processing options send `Idempotency-Key` and `X-Quote-ID` headers;
keep the same key only for the same operation and payload. Inspect `replayEnabled`
before relying on durable replay. Operation status and kind/key lookup let you
recover a retained result without starting another mutation. Results may be raw
bytes or parsed PDF JSON; operation IDs are available in
binary result headers. The clients do not automatically retry mutations.

Dynamic QR links have separate create and edit methods. Creation takes `name`,
`destination` and optional `analyticsEnabled`; edits require `expectedRevision`
and a fresh idempotency key for each distinct edit. Set `status` to `active`,
`paused` or `deleted`. A link's `redirectUrl` is the content to encode with QR
generation. Store its QR styling using `qrOptions` in an edit. Capability checks
report access to editing, analytics and active-code limits.

Analytics accept `days`, `device`, `browser`, `country` and `bot`. Browser values
are lowercase: `chrome`, `edge`, `firefox`, `safari`, `other`, `unknown`; devices
are `desktop`, `mobile`, `tablet`, `bot`, `unknown`. CSV export returns binary
content. Counts cover the retained analytics window; `recordedScans` may be absent
when analytics is unavailable. Treat scan details and exports as private data.

QR and barcode inputs support `transparent` and optional `design`. A design
contains `schemaVersion: 1`, `templateVersion: 1` and an allowed `template`:
`classic`, `rounded`, `dots` or `label`. Barcode designs support `classic` and
`label`; QR designs also support module/finder styles and an owned `logoImageId`.
Inspect `x-code-scan` and `x-code-warnings` in response headers for scan checks.

Artifact save accepts file bytes plus `kind`, `displayName`, `contentType` and
optional `requestSnapshot`/`sourceImageId`. Upload-save kinds are `qr`, `barcode`,
`pdf_render`, `pdf_merge`, `pdf_extract` and `image_transform`. Use conversion
save for document and website job outputs. Artifact and output lists return
pagination metadata and do not fetch additional pages automatically. Artifact
filters are `q` and `kind`; output filters are `q` and `type`; dynamic QR lists
use `search` and `status`. Conversion lists return up to 50 jobs for one engine.

### Capture a website

```php
$quote = $client->credits->quote(['kind' => 'website.capture', 'units' => 1]);
$key = getenv('IMAZEO_OPERATION_KEY');
if (!$key) throw new RuntimeException('IMAZEO_OPERATION_KEY must contain a UUID v4');
$job = $client->conversions->website('https://imazeo.com', $key, $quote['id'],
    ['mode' => 'viewport', 'width' => 1440, 'height' => 900]);
$status = $client->conversions->get($job['id']);
if ($status['status'] === 'completed') {
    $image = $client->conversions->download($job['id']);
    file_put_contents('website.png', $image->data);
    $artifact = $client->conversions->save($job['id']);
    echo $artifact['_id'];
}
```

File submissions use `conversions->docx($bytes, $key, $quote['id'], $options)`,
`xlsx` or `pptx`. QR/barcode and transform methods accept a second options
array with `idempotencyKey` and `quoteId`; PDF methods take those keys in their
options array.
