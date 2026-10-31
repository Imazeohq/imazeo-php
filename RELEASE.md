# SDK release procedure (`imazeo/sdk` on Packagist)

Independent repository: [Imazeohq/imazeo-php](https://github.com/Imazeohq/imazeo-php).  
Packagist package: [`imazeo/sdk`](https://packagist.org/packages/imazeo/sdk).  
Supported API prefix: `v1`. SemVer lives in this repo (`composer.json` + `src/Version.php`)
and is published from git tag `v1.0.0`.

Do not publish automatically from CI until a protected release workflow is added.
Never overwrite a published version; ship a patch instead.

## Pre-publish checks

```powershell
cd D:\projects\imazeo\sdk-php
python scripts\check-release.py
php composer.phar validate --strict
php composer.phar install
php composer.phar audit
php composer.phar test
```

## Manual publish (first release `1.0.0`)

1. Create empty public GitHub repo `Imazeohq/imazeo-php` and push this tree to `main`.
2. Tag and push: `git tag v1.0.0` then `git push origin main` and `git push origin v1.0.0`.
3. Create a [Packagist](https://packagist.org) account (GitHub login recommended).
4. Submit the package: **Submit** → repository URL  
   `https://github.com/Imazeohq/imazeo-php`
5. Confirm Packagist creates vendor `imazeo` and package `imazeo/sdk` from tag `v1.0.0`.
6. Optional but recommended: enable **GitHub Service Hook / Packagist webhook** so new tags auto-update.
7. Verify: `composer show imazeo/sdk` / https://packagist.org/packages/imazeo/sdk

## After publish

Smoke-install in a temp project:

```powershell
mkdir $env:TEMP\imazeo-php-smoke
cd $env:TEMP\imazeo-php-smoke
composer require imazeo/sdk:1.0.0
```

If the release is bad, abandon the version on Packagist and ship a reviewed patch
tag — do not reuse `v1.0.0`.
