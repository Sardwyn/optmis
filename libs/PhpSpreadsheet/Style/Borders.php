<?php

namespace PhpOffice\PhpSpreadsheet\Style;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;

class Borders extends Supervisor
{
    // Diagonal directions
    const DIAGONAL_NONE = 0;
    const DIAGONAL_UP = 1;
    const DIAGONAL_DOWN = 2;
    const DIAGONAL_BOTH = 3;

    /**
     * Left.
     */
    protected Border $left;

    /**
     * Right.
     */
    protected Border $right;

    /**
     * Top.
     */
    protected Border $top;

    /**
     * Bottom.
     */
    protected Border $bottom;

    /**
     * Diagonal.
     */
    protected Border $diagonal;

    /**
     * DiagonalDirection.
     */
    protected int $diagonalDirection;

    /**
     * All borders pseudo-border. Only applies to supervisor.
     */
    protected Border $allBorders;

    /**
     * Outline pseudo-border. Only applies to supervisor.
     */
    protected Border $outline;

    /**
     * Inside pseudo-border. Only applies to supervisor.
     */
    protected Border $inside;

    /**
     * Vertical pseudo-border. Only applies to supervisor.
     */
    protected Border $vertical;

    /**
     * Horizontal pseudo-border. Only applies to supervisor.
     */
    protected Border $horizontal;

    /**
     * Create a new Borders.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(bool $isSupervisor = false, bool $isConditional = false)
    {
        // Supervisor?
        parent::__construct($isSupervisor);

        // Initialise values
        $this->left = new Border($isSupervisor, $isConditional);
        $this->right = new Border($isSupervisor, $isConditional);
        $this->top = new Border($isSupervisor, $isConditional);
        $this->bottom = new Border($isSupervisor, $isConditional);
        $this->diagonal = new Border($isSupervisor, $isConditional);
        $this->diagonalDirection = self::DIAGONAL_NONE;

        // Specially for supervisor
        if ($isSupervisor) {
            // Initialize pseudo-borders
            $this->allBorders = new Border(true, $isConditional);
            $this->outline = new Border(true, $isConditional);
            $this->inside = new Border(true, $isConditional);
            $this->vertical = new Border(true, $isConditional);
            $this->horizontal = new Border(true, $isConditional);

            // bind parent if we are a supervisor
            $this->left->bindParent($this, 'left');
            $this->right->bindParent($this, 'right');
            $this->top->bindParent($this, 'top');
            $this->bottom->bindParent($this, 'bottom');
            $this->diagonal->bindParent($this, 'diagonal');
            $this->allBorders->bindParent($this, 'allBorders');
            $this->outline->bindParent($this, 'outline');
            $this->inside->bindParent($this, 'inside');
            $this->vertical->bindParent($this, 'vertical');
            $this->horizontal->bindParent($this, 'horizontal');
        }
    }

    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    public function getSharedComponent(): self
    {
        /** @var Style $parent */
        $parent = $this->parent;

        return $parent->getSharedComponent()->getBorders();
    }

    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{borders: mixed[]}
     */
    public function getStyleArray(array $array): array
    {
        return ['borders' => $array];
    }

    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getBorders()->applyFromArray(
     *         [
     *             'bottom' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ],
     *             'top' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ]
     *         ]
     * );
     * </code>
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getBorders()->applyFromArray(
     *         [
     *             'allBorders' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ]
     *         ]
     * );
     * </code>
     *
     * @param mixed[] $styleArray Array containing style information
     *
     * @return $this
     */
    public function applyFromArray(array $styleArray): static
    {
        if ($this->isSupervisor) {
            $this->getActiveSheet()->getStyle($this->getSelectedCells())->applyFromArray($this->getStyleArray($styleArray));
        } else {
            /** @var array{left?: float[], right?: float[], top?: float[], bottom?: float[], diagonal?: mixed[], diagonalDirection?: int, allBorders?: mixed[][]} $styleArray */
            if (isset($styleArray['left'])) {
                $this->getLeft()->applyFromArray($styleArray['left']);
            }
            if (isset($styleArray['right'])) {
                $this->getRight()->applyFromArray($styleArray['right']);
            }
            if (isset($styleArray['top'])) {
                $this->getTop()->applyFromArray($styleArray['top']);
            }
            if (isset($styleArray['bottom'])) {
                $this->getBottom()->applyFromArray($styleArray['bottom']);
            }
            if (isset($styleArray['diagonal'])) {
                $this->getDiagonal()->applyFromArray($styleArray['diagonal']);
            }
            if (isset($styleArray['diagonalDirection'])) {
                $this->setDiagonalDirection($styleArray['diagonalDirection']);
            }
            if (isset($styleArray['allBorders'])) {
                $this->getLeft()->applyFromArray($styleArray['allBorders']);
                $this->getRight()->applyFromArray($styleArray['allBorders']);
                $this->getTop()->applyFromArray($styleArray['allBorders']);
                $this->getBottom()->applyFromArray($styleArray['allBorders']);
            }
        }

        return $this;
    }

    /**
     * Get Left.
     */
    public function getLeft(): Border
    {
        return $this->left;
    }

    /**
     * Get Right.
     */
    public function getRight(): Border
    {
        return $this->right;
    }

    /**
     * Get Top.
     */
    public function getTop(): Border
    {
        return $this->top;
    }

    /**
     * Get Bottom.
     */
    public function getBottom(): Border
    {
        return $this->bottom;
    }

    /**
     * Get Diagonal.
     */
    public function getDiagonal(): Border
    {
        return $this->diagonal;
    }

    /**
     * Get AllBorders (pseudo-border). Only applies to supervisor.
     */
    public function getAllBorders(): Border
    {
        if (!$this->isSupervisor) {
            throw new PhpSpreadsheetException('Can only get pseudo-border for supervisor.');
        }

        return $this->allBorders;
    }

    /**
     * Get Outline (pseudo-border). Only applies to supervisor.
     */
    public function getOutline(): Border
    {
        if (!$this->isSupervisor) {
            throw new PhpSpreadsheetException('Can only get pseudo-border for supervisor.');
        }

        return $this->outline;
    }

    /**
     * Get Inside (pseudo-border). Only applies to supervisor.
     */
    public function getInside(): Border
    {
        if (!$this->isSupervisor) {
            throw new PhpSpreadsheetException('Can only get pseudo-border for supervisor.');
        }

        return $this->inside;
    }

    /**
     * Get Vertical (pseudo-border). Only applies to supervisor.
     */
    public function getVertical(): Border
    {
        if (!$this->isSupervisor) {
            throw new PhpSpreadsheetException('Can only get pseudo-border for supervisor.');
        }

        return $this->vertical;
    }

    /**
     * Get Horizontal (pseudo-border). Only applies to supervisor.
     */
    public function getHorizontal(): Border
    {
        if (!$this->isSupervisor) {
            throw new PhpSpreadsheetException('Can only get pseudo-border for supervisor.');
        }

        return $this->horizontal;
    }

    /**
     * Get DiagonalDirection.
     */
    public function getDiagonalDirection(): int
    {
        if ($this->isSupervisor) {
            return $this->getSharedComponent()->getDiagonalDirection();
        }

        return $this->diagonalDirection;
    }

    /**
     * Set DiagonalDirection.
     *
     * @param int $direction see self::DIAGONAL_*
     *
     * @return $this
     */
    public function setDiagonalDirection(int $direction): static
    {
        if ($this->isSupervisor) {
            $styleArray = $this->getStyleArray(['diagonalDirection' => $direction]);
            $this->getActiveSheet()->getStyle($this->getSelectedCells())->applyFromArray($styleArray);
        } else {
            $this->diagonalDirection = $direction;
        }

        return $this;
    }

    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function getHashCode(): string
    {
        if ($this->isSupervisor) {
            return $this->getSharedComponent()->getHashcode();
        }

        return md5(
            $this->getLeft()->getHashCode()
            . $this->getRight()->getHashCode()
            . $this->getTop()->getHashCode()
            . $this->getBottom()->getHashCode()
            . $this->getDiagonal()->getHashCode()
            . $this->getDiagonalDirection()
            . __CLASS__
        );
    }

    /** @return mixed[][] */
    protected function exportArray1(): array
    {
        $exportedArray = [];
        $this->exportArray2($exportedArray, 'bottom', $this->getBottom());
        $this->exportArray2($exportedArray, 'diagonal', $this->getDiagonal());
        $this->exportArray2($exportedArray, 'diagonalDirection', $this->getDiagonalDirection());
        $this->exportArray2($exportedArray, 'left', $this->getLeft());
        $this->exportArray2($exportedArray, 'right', $this->getRight());
        $this->exportArray2($exportedArray, 'top', $this->getTop());
        /** @var mixed[][] $exportedArray */

        return $exportedArray;
    }
}
