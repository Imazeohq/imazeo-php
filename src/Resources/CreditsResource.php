<?php
declare(strict_types=1);
namespace Imazeo\Resources;
use Imazeo\Http\Multipart;
use Imazeo\Types\BinaryResult;
use Imazeo\Types\Page;
final class CreditsResource extends ProjectResource
{
    public function quote(array $input): array { return $this->json('POST', ['credits', 'quote'], $input); }
}
