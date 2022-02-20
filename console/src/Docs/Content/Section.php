<?php
declare(strict_types=1);

namespace App\Docs\Content;

/**
 * Represents a section of a page.
 */
class Section
{
    /**
     * The section level.
     *
     * @var int
     */
    protected $level;

    /**
     * The section position.
     *
     * @var int
     */
    protected $position;

    /**
     * The section hierarchy.
     *
     * @var string[]
     */
    protected $hierarchy;

    /**
     * The section anchor.
     *
     * @var string
     */
    protected $anchor;

    /**
     * The section title.
     *
     * @var string
     */
    protected $title;

    /**
     * The section content.
     *
     * @var string
     */
    protected $content;

    /**
     * Returns the section level.
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Returns the section position.
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Returns the section hierarchy.
     *
     * @return string[]
     */
    public function getHierarchy(): array
    {
        return $this->hierarchy;
    }

    /**
     * Returns the section anchor.
     *
     * @return string
     */
    public function getAnchor(): string
    {
        return $this->anchor;
    }

    /**
     * Returns the section title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the section content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Constructor.
     *
     * @param int $level The section level.
     * @param int $position The section position.
     * @param string[] $hierarchy The section hierarchy.
     * @param string $anchor The section anchor.
     * @param string $title The section title.
     * @param string $content The section content.
     */
    public function __construct(
        int $level,
        int $position,
        array $hierarchy,
        string $anchor,
        string $title,
        string $content
    ) {
        $this->level = $level;
        $this->position = $position;
        $this->hierarchy = $hierarchy;
        $this->anchor = $anchor;
        $this->title = $title;
        $this->content = $content;
    }
}
