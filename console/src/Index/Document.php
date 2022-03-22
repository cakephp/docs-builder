<?php
declare(strict_types=1);

namespace App\Index;

/**
 * Represents an Elasticsearch document.
 */
class Document
{
    /**
     * The value that identifies a low priority document.
     *
     * @var string
     */
    public const PRIORITY_LOW = 'low';

    /**
     * The value that identifies a normal priority document.
     *
     * @var string
     */
    public const PRIORITY_NORMAL = 'normal';

    /**
     * The value that identifies an internal document.
     *
     * @var string
     */
    public const TYPE_INTERNAL = 'internal';

    /**
     * The value that identifies an external document.
     *
     * @var string
     */
    public const TYPE_EXTERNAL = 'external';

    /**
     * The document ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The document data.
     *
     * @var array<string, mixed>
     */
    protected $data;

    /**
     * Returns the document ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the document data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Constructor.
     *
     * @param string $id The document ID.
     * @param array<string, mixed> $data The document data.
     */
    public function __construct(string $id, array $data)
    {
        $this->id = $id;
        $this->data = $data;
    }

    /**
     * Returns the array representation of the document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id] + $this->data;
    }
}
