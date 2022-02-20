<?php
declare(strict_types=1);

namespace App\Docs\Content;

/**
 * Represents a page.
 */
class Page
{
    /**
     * The page hierarchy.
     *
     * @var string[]
     */
    protected $hierarchy;

    /**
     * The page sections.
     *
     * @var \App\Docs\Content\Section[]
     */
    protected $sections;

    /**
     * Returns the page hierarchy.
     *
     * @return string[]
     */
    public function getHierarchy(): array
    {
        return $this->hierarchy;
    }

    /**
     * Returns the page sections.
     *
     * @return \App\Docs\Content\Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Constructor.
     *
     * @param string[] $hierarchy The page hierarchy.
     * @param \App\Docs\Content\Section[] $sections The page sections.
     */
    public function __construct(array $hierarchy, array $sections)
    {
        $this->hierarchy = $hierarchy;
        $this->sections = $sections;
    }
}
