<?php

namespace Inertia\Props;

use Inertia\Contracts\ProvidesScrollMetadata;
use InvalidArgumentException;
use Spark\Contracts\Support\Arrayable;
use Spark\Utils\Paginator;

class ScrollMetadata implements Arrayable, ProvidesScrollMetadata
{
    public function __construct(
        protected string $pageName,
        protected int|string|null $previousPage = null,
        protected int|string|null $nextPage = null,
        protected int|string|null $currentPage = null
    ) {
    }

    public static function fromPaginator(mixed $value): self
    {
        if (!$value instanceof Paginator) {
            throw new InvalidArgumentException(
                'The scroll prop value is not a TinyMVC paginator. Provide a metadata callback or ProvidesScrollMetadata instance.'
            );
        }

        return new self(
            $value->keyword(),
            $value->page() > 1 ? $value->page() - 1 : null,
            $value->page() < $value->pages() ? $value->page() + 1 : null,
            $value->page()
        );
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    public function getPreviousPage(): int|string|null
    {
        return $this->previousPage;
    }

    public function getNextPage(): int|string|null
    {
        return $this->nextPage;
    }

    public function getCurrentPage(): int|string|null
    {
        return $this->currentPage;
    }

    public function toArray(): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
        ];
    }
}
