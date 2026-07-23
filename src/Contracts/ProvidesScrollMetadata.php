<?php

namespace Inertia\Contracts;

interface ProvidesScrollMetadata
{
    public function getPageName(): string;

    public function getPreviousPage(): int|string|null;

    public function getNextPage(): int|string|null;

    public function getCurrentPage(): int|string|null;
}
