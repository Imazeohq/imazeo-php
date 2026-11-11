<?php

declare(strict_types=1);

/**
 * Minimal server-side example.
 * Requires: IMAZEO_API_KEY, optional IMAZEO_BASE_URL, IMAZEO_PROJECT_ID
 *
 * Usage: php examples/qr.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Imazeo\Imazeo;

$apiKey = getenv('IMAZEO_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set IMAZEO_API_KEY\n");
    exit(1);
}

$options = [
    'apiKey' => $apiKey,
    'baseUrl' => getenv('IMAZEO_BASE_URL') ?: 'https://api.imazeo.com',
];
$projectId = getenv('IMAZEO_PROJECT_ID');
if (is_string($projectId) && $projectId !== '') {
    $options['projectId'] = $projectId;
}

$client = new Imazeo($options);

$ctx = $client->context->get();
echo 'project ' . $ctx['projectId'] . ' env ' . $ctx['environment'] . PHP_EOL;

$qr = $client->qr->generate([
    'content' => 'https://imazeo.com',
    'size' => 256,
    'format' => 'png',
]);
file_put_contents('qr.png', $qr->data);
echo 'wrote qr.png ' . $qr->contentType . PHP_EOL;
