<?php

declare(strict_types=1);

/**
 * Upload an image and run an ephemeral transform.
 *
 * Usage: php examples/upload_transform.php <image-path>
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Imazeo\Imazeo;

$apiKey = getenv('IMAZEO_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set IMAZEO_API_KEY\n");
    exit(1);
}

$inputPath = $argv[1] ?? null;
if ($inputPath === null || $inputPath === '') {
    fwrite(STDERR, "Usage: php examples/upload_transform.php <image>\n");
    exit(1);
}

$bytes = file_get_contents($inputPath);
if ($bytes === false) {
    fwrite(STDERR, "Failed to read {$inputPath}\n");
    exit(1);
}

$options = [
    'apiKey' => $apiKey,
    'baseUrl' => getenv('IMAZEO_BASE_URL') ?: 'http://localhost:3001',
];
$projectId = getenv('IMAZEO_PROJECT_ID');
if (is_string($projectId) && $projectId !== '') {
    $options['projectId'] = $projectId;
}

$client = new Imazeo($options);

$image = $client->images->upload([
    'data' => $bytes,
    'filename' => 'input.bin',
    'contentType' => 'application/octet-stream',
]);
echo 'uploaded ' . $image['_id'] . PHP_EOL;

$transformed = $client->transforms->create([
    'imageId' => $image['_id'],
    'mode' => 'EPHEMERAL',
    'transform' => ['width' => 800, 'format' => 'webp', 'quality' => 80],
]);
file_put_contents('out.webp', $transformed->data);
echo 'wrote out.webp ' . ($transformed->transformationId ?? '') . PHP_EOL;
