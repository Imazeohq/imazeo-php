<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class OutputsResource extends ProjectResource
{
    public function summary(): array { return $this->json('GET', ['outputs', 'summary']); }
    public function list(array $params = []): Page { return $this->page(['outputs'], $params); }
}
