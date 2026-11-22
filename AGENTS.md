# imazeo/sdk (PHP) — agent notes

Independent public package for the Imazeo API-key client.

- Contract: published API `v1` (see [GUIDE.md](GUIDE.md))
- Scope: project API-key context, media, credit quotes, operations, conversions, dynamic QR, artifacts and outputs
- Do not add admin or customer JWT portal APIs
- Do not publish without maintainer approval — see [RELEASE.md](RELEASE.md)
- Never commit Packagist tokens
- Release: `$env:RELEASE_MODE="true"; npm run release` (syncs `composer.json` + `Version.php`); Actions notifies Packagist
