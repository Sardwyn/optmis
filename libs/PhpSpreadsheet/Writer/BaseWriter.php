<?php

namespace PhpOffice\PhpSpreadsheet\Writer;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;

abstract class BaseWriter implements IWriter
{
    /**
     * Write charts that are defined in the workbook?
     * Identifies whether the Writer should write definitions for any charts that exist in the PhpSpreadsheet object.
     */
    protected bool $includeCharts = false;

    /**
     * Pre-calculate formulas
     * Forces PhpSpreadsheet to recalculate all formulae in a workbook when saving, so that the pre-calculated values are
     * immediately available to MS Excel or other office spreadsheet viewer when opening the file.
     */
    protected bool $preCalculateFormulas = true;

    /**
     * Table formats
     * Enables table formats in writer, disabled here, must be enabled in writer via a setter.
     */
    protected bool $tableFormats = false;

    /**
     * Conditional Formatting
     * Enables conditional formatting in writer, disabled here, must be enabled in writer via a setter.
     */
    protected bool $conditionalFormatting = false;

    /**
     * Use disk caching where possible?
     */
    private bool $useDiskCaching = false;

    /**
     * Disk caching directory.
     */
    private string $diskCachingDirectory = './';

    /**
     * @var resource
     */
    protected $fileHandle;

    private bool $shouldCloseFile;

    public function getIncludeCharts(): bool
    {
        return $this->includeCharts;
    }

    public function setIncludeCharts(bool $includeCharts): self
    {
        $this->includeCharts = $includeCharts;

        return $this;
    }

    public function getPreCalculateFormulas(): bool
    {
        return $this->preCalculateFormulas;
    }

    public function setPreCalculateFormulas(bool $precalculateFormulas): self
    {
        $this->preCalculateFormulas = $precalculateFormulas;

        return $this;
    }

    public function getTableFormats(): bool
    {
        return $this->tableFormats;
    }

    public function setTableFormats(bool $tableFormats): self
    {
        if ($tableFormats) {
            throw new PhpSpreadsheetException('Table formatting not implemented for this writer');
        }

        return $this;
    }

    public function getConditionalFormatting(): bool
    {
        return $this->conditionalFormatting;
    }

    public function setConditionalFormatting(bool $conditionalFormatting): self
    {
        if ($conditionalFormatting) {
            throw new PhpSpreadsheetException('Conditional Formatting not implemented for this writer');
        }

        return $this;
    }

    public function getUseDiskCaching(): bool
    {
        return $this->useDiskCaching;
    }

    public function setUseDiskCaching(bool $useDiskCache, ?string $cacheDirectory = null): self
    {
        $this->useDiskCaching = $useDiskCache;

        if ($cacheDirectory !== null) {
            if (is_dir($cacheDirectory)) {
                $this->diskCachingDirectory = $cacheDirectory;
            } else {
                throw new Exception("Directory does not exist: $cacheDirectory");
            }
        }

        return $this;
    }

    public function getDiskCachingDirectory(): string
    {
        return $this->diskCachingDirectory;
    }

    protected function processFlags(int $flags): void
    {
        if (((bool) ($flags & self::SAVE_WITH_CHARTS)) === true) {
            $this->setIncludeCharts(true);
        }
        if (((bool) ($flags & self::DISABLE_PRECALCULATE_FORMULAE)) === true) {
            $this->setPreCalculateFormulas(false);
        }
    }

    /**
     * Open file handle.
     *
     * @param resource|string $filename
     */
    public function openFileHandle($filename): void
    {
        if (!is_string($filename)) {
            $this->fileHandle = $filename;
            $this->shouldCloseFile = false;

            return;
        }

        $mode = 'wb';
        $scheme = parse_url($filename, PHP_URL_SCHEME);
        if ($scheme === 's3') {
            // @codeCoverageIgnoreStart
            $mode = 'w';
            // @codeCoverageIgnoreEnd
        }
        $fileHandle = $filename ? fopen($filename, $mode) : false;
        if ($fileHandle === false) {
            throw new Exception('Could not open file "' . $filename . '" for writing.');
        }

        $this->fileHandle = $fileHandle;
        $this->shouldCloseFile = true;
    }

    /**
     * Close file handle only if we opened it ourselves.
     */
    protected function maybeCloseFileHandle(): void
    {
        if ($this->shouldCloseFile) {
            if (!fclose($this->fileHandle)) {
                throw new Exception('Could not close file after writing.');
            }
        }
    }
}
