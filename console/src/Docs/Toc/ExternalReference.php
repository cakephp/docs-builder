<?php
declare(strict_types=1);

namespace App\Docs\Toc;

/**
 * Represents an external reference.
 */
class ExternalReference
{
    /**
     * The external reference URL.
     *
     * @var string
     */
    protected $url;

    /**
     * The external reference title.
     *
     * @var string
     */
    protected $title;

    /**
     * Returns the external reference URL.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the external reference title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Constructor.
     *
     * @param string $url The external reference URL.
     * @param string $title The external reference title.
     */
    public function __construct(string $url, string $title)
    {
        $this->url = $url;
        $this->title = $title;
    }
}
