#!/usr/bin/env node
/** Keep composer.json version and Version::SDK_VERSION in sync after release-it bumps package.json. */
import { readFileSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const version = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8')).version;

const composerPath = join(root, 'composer.json');
const composer = JSON.parse(readFileSync(composerPath, 'utf8'));
composer.version = version;
writeFileSync(composerPath, `${JSON.stringify(composer, null, 2)}\n`, 'utf8');

const versionPhpPath = join(root, 'src', 'Version.php');
const versionPhp = readFileSync(versionPhpPath, 'utf8');
const nextVersionPhp = versionPhp.replace(
  /SDK_VERSION\s*=\s*'[^']+'/,
  `SDK_VERSION = '${version}'`,
);
if (!nextVersionPhp.includes(`SDK_VERSION = '${version}'`)) {
  console.error('sync-sdk-version: failed to update Version.php');
  process.exit(1);
}
writeFileSync(versionPhpPath, nextVersionPhp, 'utf8');

const add = spawnSync(
  'git',
  ['add', 'composer.json', 'src/Version.php', 'package.json'],
  {
    cwd: root,
    stdio: 'inherit',
    shell: false,
  },
);
if (add.status !== 0) process.exit(add.status ?? 1);
console.log(`sync-sdk-version: ${version}`);
