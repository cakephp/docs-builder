<?php
declare(strict_types=1);

namespace App\Docs\Toc;

/**
 * Represents table of contents.
 */
class Toc
{
    /**
     * The internal references.
     *
     * @var \App\Docs\Toc\InternalReference[]
     */
    protected $internalReferences;

    /**
     * The external references.
     *
     * @var \App\Docs\Toc\ExternalReference[]
     */
    protected $externalReferences;

    /**
     * Returns the internal references.
     *
     * @return \App\Docs\Toc\InternalReference[]
     */
    public function getInternalReferences(): array
    {
        return $this->internalReferences;
    }

    /**
     * Returns the external references.
     *
     * @return \App\Docs\Toc\ExternalReference[]
     */
    public function getExternalReferences(): array
    {
        return $this->externalReferences;
    }

    /**
     * Constructor.
     *
     * @param \App\Docs\Toc\InternalReference[] $internalReferences The internal references.
     * @param \App\Docs\Toc\ExternalReference[] $externalReferences The external references.
     */
    public function __construct(array $internalReferences, array $externalReferences)
    {
        $this->internalReferences = $internalReferences;
        $this->externalReferences = $externalReferences;
    }
}
