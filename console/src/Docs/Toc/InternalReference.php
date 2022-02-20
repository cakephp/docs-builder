<?php
declare(strict_types=1);

namespace App\Docs\Toc;

/**
 * Represents an internal reference.
 */
class InternalReference
{
    /**
     * The internal reference URL.
     *
     * @var string
     */
    protected $url;

    /**
     * The internal reference file path.
     *
     * @var string
     */
    protected $filePath;

    /**
     * Returns the internal reference URL.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the internal reference file path.
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Constructor.
     *
     * @param string $url The internal reference URL.
     * @param string $filePath The internal reference file path.
     */
    public function __construct(string $url, string $filePath)
    {
        $this->url = $url;
        $this->filePath = $filePath;
    }
}
