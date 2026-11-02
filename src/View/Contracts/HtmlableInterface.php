<?php

declare(strict_types=1);

namespace Rocket\View\Contracts;

/**
 * Interface HtmlableInterface
 *
 * Denotes an object whose string representation is trusted HTML and should not be escaped.
 */
interface HtmlableInterface
{
    /**
     * Get content as a string of HTML.
     */
    public function toHtml(): string;
}

