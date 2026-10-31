# SDK-3 plan — PHP

**Status: implemented (1.0.0)**

1. PSR-4 package with Composer (`Imazeo\\` → `src/`) — done
2. Curl HTTP transport with injectable handler for tests — done
3. Sync client, multipart uploads (`X-Upload-Length`), typed results — done
4. PHPUnit with mocked responses — done
5. PHP `^8.2` — done

Scope: API-key resources only (`context`, `images`, `transforms`, `qr`,
`barcodes`, `pdf`). No admin/JWT/tool-artifacts/outputs.

Reference: `../sdk-typescript/` and `../docs/SDK_BEHAVIOR.md`.
Package name `imazeo/sdk` is free on Packagist (vendor `imazeo` empty as of
prep). Publish by tagging `v1.0.0` on `manishbalodia/imazeo-php` and submitting
that repo on Packagist.
