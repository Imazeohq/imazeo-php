<?php

declare(strict_types=1);

namespace Imazeo\Types;

/**
 * Paginated list result.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $limit,
        public readonly int $total,
        public readonly int $pages,
        public readonly string $requestId,
    ) {
    }
}
