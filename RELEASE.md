# SDK release procedure (`imazeo/sdk` on Packagist)

Independent repository: [Imazeohq/imazeo-php](https://github.com/Imazeohq/imazeo-php).  
Packagist package: [`imazeo/sdk`](https://packagist.org/packages/imazeo/sdk).  
Supported API prefix: `v1`. SemVer lives in `composer.json`, `src/Version.php`, and `package.json`
(kept in sync by the release script). Published from git tag `v*`.

Never reuse a published tag; ship a patch instead.

## One-time: Packagist

1. On Packagist → package **imazeo/sdk** → enable the **GitHub webhook**.
2. GitHub → **Settings → Secrets** for `Imazeohq/imazeo-php`:

| Secret | Value |
| --- | --- |
| `PACKAGIST_USERNAME` | Packagist username |
| `PACKAGIST_TOKEN` | Packagist API token |

## Release (PowerShell)

Commit your work on `main` first (clean working tree required). One-time: `npm install`.

```powershell
$env:RELEASE_MODE = "true"
npm run release
```

You will be prompted for version bump and GitHub Release.  
This syncs versions, commits, tags `vX.Y.Z`, and pushes.  
**Publish Packagist** then verifies the tag and notifies Packagist.

## Release from GitHub only

If the version bump is already on `main`:

1. **Releases** → Draft a new release → tag `vX.Y.Z` → Publish  
   or
2. **Actions** → **Publish Packagist** → **Run workflow**

## After publish

Verify: https://packagist.org/packages/imazeo/sdk
