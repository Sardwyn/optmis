<?php

namespace PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer;

class SpgrContainer
{
    /**
     * Parent Shape Group Container.
     */
    private ?self $parent = null;

    /**
     * Shape Container collection.
     *
     * @var mixed[]
     */
    private array $children = [];

    /**
     * Set parent Shape Group Container.
     */
    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Get the parent Shape Group Container if any.
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Add a child. This will be either spgrContainer or spContainer.
     *
     * @param SpgrContainer|SpgrContainer\SpContainer $child child to be added
     */
    public function addChild(mixed $child): void
    {
        $this->children[] = $child;
        $child->setParent($this);
    }

    /**
     * Get collection of Shape Containers.
     *
     * @return mixed[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Recursively get all spContainers within this spgrContainer.
     *
     * @return SpgrContainer\SpContainer[]
     */
    public function getAllSpContainers(): array
    {
        $allSpContainers = [];

        foreach ($this->children as $child) {
            if ($child instanceof self) {
                $allSpContainers = array_merge($allSpContainers, $child->getAllSpContainers());
            } else {
                $allSpContainers[] = $child;
            }
        }
        /** @var SpgrContainer\SpContainer[] $allSpContainers */

        return $allSpContainers;
    }
}
