<?php

namespace PhpOffice\PhpSpreadsheet\Writer\Xls;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Escher as SharedEscher;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer\SpgrContainer;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer\SpgrContainer\SpContainer;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer\BSE;
use PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer\BSE\Blip;

class Escher
{
    /**
     * The object we are writing.
     */
    private Blip|BSE|BstoreContainer|DgContainer|DggContainer|Escher|SpContainer|SpgrContainer|SharedEscher $object;

    /**
     * The written binary data.
     */
    private string $data;

    /**
     * Shape offsets. Positions in binary stream where a new shape record begins.
     *
     * @var int[]
     */
    private array $spOffsets;

    /**
     * Shape types.
     *
     * @var mixed[]
     */
    private array $spTypes;

    /**
     * Constructor.
     */
    public function __construct(Blip|BSE|BstoreContainer|DgContainer|DggContainer|self|SpContainer|SpgrContainer|SharedEscher $object)
    {
        $this->object = $object;
    }

    /**
     * Process the object to be written.
     */
    public function close(): string
    {
        // initialize
        $this->data = '';

        switch ($this->object::class) {
            case SharedEscher::class:
                if ($dggContainer = $this->object->getDggContainer()) {
                    $writer = new self($dggContainer);
                    $this->data = $writer->close();
                } elseif ($dgContainer = $this->object->getDgContainer()) {
                    $writer = new self($dgContainer);
                    $this->data = $writer->close();
                    $this->spOffsets = $writer->getSpOffsets();
                    $this->spTypes = $writer->getSpTypes();
                }

                break;
            case DggContainer::class:
                // this is a container record

                // initialize
                $innerData = '';

                // write the dgg
                $recVer = 0x0;
                $recInstance = 0x0000;
                $recType = 0xF006;

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                // dgg data
                $dggData
                    = pack(
                        'VVVV',
                        $this->object->getSpIdMax(), // maximum shape identifier increased by one
                        $this->object->getCDgSaved() + 1, // number of file identifier clusters increased by one
                        $this->object->getCSpSaved(),
                        $this->object->getCDgSaved() // count total number of drawings saved
                    );

                // add file identifier clusters (one per drawing)
                $IDCLs = $this->object->getIDCLs();

                foreach ($IDCLs as $dgId => $maxReducedSpId) {
                    /** @var int $maxReducedSpId */
                    $dggData .= pack('VV', $dgId, $maxReducedSpId + 1);
                }

                $header = pack('vvV', $recVerInstance, $recType, strlen($dggData));
                $innerData .= $header . $dggData;

                // write the bstoreContainer
                if ($bstoreContainer = $this->object->getBstoreContainer()) {
                    $writer = new self($bstoreContainer);
                    $innerData .= $writer->close();
                }

                // write the record
                $recVer = 0xF;
                $recInstance = 0x0000;
                $recType = 0xF000;
                $length = strlen($innerData);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header . $innerData;

                break;
            case BstoreContainer::class:
                // this is a container record

                // initialize
                $innerData = '';

                // treat the inner data
                if ($BSECollection = $this->object->getBSECollection()) {
                    foreach ($BSECollection as $BSE) {
                        $writer = new self($BSE);
                        $innerData .= $writer->close();
                    }
                }

                // write the record
                $recVer = 0xF;
                $recInstance = count($this->object->getBSECollection());
                $recType = 0xF001;
                $length = strlen($innerData);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header . $innerData;

                break;
            case BSE::class:
                // this is a semi-container record

                // initialize
                $innerData = '';

                // here we treat the inner data
                if ($blip = $this->object->getBlip()) {
                    $writer = new self($blip);
                    $innerData .= $writer->close();
                }

                // initialize
                $data = '';

                $btWin32 = $this->object->getBlipType();
                $btMacOS = $this->object->getBlipType();
                $data .= pack('CC', $btWin32, $btMacOS);

                $rgbUid = pack('VVVV', 0, 0, 0, 0); // todo
                $data .= $rgbUid;

                $tag = 0;
                $size = strlen($innerData);
                $cRef = 1;
                $foDelay = 0; //todo
                $unused1 = 0x0;
                $cbName = 0x0;
                $unused2 = 0x0;
                $unused3 = 0x0;
                $data .= pack('vVVVCCCC', $tag, $size, $cRef, $foDelay, $unused1, $cbName, $unused2, $unused3);

                $data .= $innerData;

                // write the record
                $recVer = 0x2;
                $recInstance = $this->object->getBlipType();
                $recType = 0xF007;
                $length = strlen($data);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header;

                $this->data .= $data;

                break;
            case Blip::class:
                // this is an atom record

                // write the record
                switch ($this->object->getParent()->getBlipType()) {
                    case BSE::BLIPTYPE_JPEG:
                        // initialize
                        $innerData = '';

                        $rgbUid1 = pack('VVVV', 0, 0, 0, 0); // todo
                        $innerData .= $rgbUid1;

                        $tag = 0xFF; // todo
                        $innerData .= pack('C', $tag);

                        $innerData .= $this->object->getData();

                        $recVer = 0x0;
                        $recInstance = 0x46A;
                        $recType = 0xF01D;
                        $length = strlen($innerData);

                        $recVerInstance = $recVer;
                        $recVerInstance |= $recInstance << 4;

                        $header = pack('vvV', $recVerInstance, $recType, $length);

                        $this->data = $header;

                        $this->data .= $innerData;

                        break;
                    case BSE::BLIPTYPE_PNG:
                        // initialize
                        $innerData = '';

                        $rgbUid1 = pack('VVVV', 0, 0, 0, 0); // todo
                        $innerData .= $rgbUid1;

                        $tag = 0xFF; // todo
                        $innerData .= pack('C', $tag);

                        $innerData .= $this->object->getData();

                        $recVer = 0x0;
                        $recInstance = 0x6E0;
                        $recType = 0xF01E;
                        $length = strlen($innerData);

                        $recVerInstance = $recVer;
                        $recVerInstance |= $recInstance << 4;

                        $header = pack('vvV', $recVerInstance, $recType, $length);

                        $this->data = $header;

                        $this->data .= $innerData;

                        break;
                }

                break;
            case DgContainer::class:
                // this is a container record

                // initialize
                $innerData = '';

                // write the dg
                $recVer = 0x0;
                $recInstance = $this->object->getDgId();
                $recType = 0xF008;
                $length = 8;

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                // number of shapes in this drawing (including group shape)
                $countShapes = count($this->object->getSpgrContainerOrThrow()->getChildren());
                $innerData .= $header . pack('VV', $countShapes, $this->object->getLastSpId());

                // write the spgrContainer
                if ($spgrContainer = $this->object->getSpgrContainer()) {
                    $writer = new self($spgrContainer);
                    $innerData .= $writer->close();

                    // get the shape offsets relative to the spgrContainer record
                    $spOffsets = $writer->getSpOffsets();
                    $spTypes = $writer->getSpTypes();

                    // save the shape offsets relative to dgContainer
                    foreach ($spOffsets as &$spOffset) {
                        $spOffset += 24; // add length of dgContainer header data (8 bytes) plus dg data (16 bytes)
                    }

                    $this->spOffsets = $spOffsets;
                    $this->spTypes = $spTypes;
                }

                // write the record
                $recVer = 0xF;
                $recInstance = 0x0000;
                $recType = 0xF002;
                $length = strlen($innerData);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header . $innerData;

                break;
            case SpgrContainer::class:
                // this is a container record

                // initialize
                $innerData = '';

                // initialize spape offsets
                $totalSize = 8;
                $spOffsets = [];
                $spTypes = [];

                // treat the inner data
                foreach ($this->object->getChildren() as $spContainer) {
                    /** @var Blip|BSE|BstoreContainer|DgContainer|DggContainer|SharedEscher|SpContainer|SpgrContainer $spContainer */
                    $writer = new self($spContainer);
                    $spData = $writer->close();
                    $innerData .= $spData;

                    // save the shape offsets (where new shape records begin)
                    $totalSize += strlen($spData);
                    $spOffsets[] = $totalSize;

                    $spTypes = array_merge($spTypes, $writer->getSpTypes());
                }

                // write the record
                $recVer = 0xF;
                $recInstance = 0x0000;
                $recType = 0xF003;
                $length = strlen($innerData);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header . $innerData;
                $this->spOffsets = $spOffsets;
                $this->spTypes = $spTypes;

                break;
            case SpContainer::class:
                // initialize
                $data = '';

                // build the data

                // write group shape record, if necessary?
                if ($this->object->getSpgr()) {
                    $recVer = 0x1;
                    $recInstance = 0x0000;
                    $recType = 0xF009;
                    $length = 0x00000010;

                    $recVerInstance = $recVer;
                    $recVerInstance |= $recInstance << 4;

                    $header = pack('vvV', $recVerInstance, $recType, $length);

                    $data .= $header . pack('VVVV', 0, 0, 0, 0);
                }
                $this->spTypes[] = ($this->object->getSpType());

                // write the shape record
                $recVer = 0x2;
                $recInstance = $this->object->getSpType(); // shape type
                $recType = 0xF00A;
                $length = 0x00000008;

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $data .= $header . pack('VV', $this->object->getSpId(), $this->object->getSpgr() ? 0x0005 : 0x0A00);

                // the options
                if ($this->object->getOPTCollection()) {
                    $optData = '';

                    $recVer = 0x3;
                    $recInstance = count($this->object->getOPTCollection());
                    $recType = 0xF00B;
                    foreach ($this->object->getOPTCollection() as $property => $value) {
                        $optData .= pack('vV', $property, $value);
                    }
                    $length = strlen($optData);

                    $recVerInstance = $recVer;
                    $recVerInstance |= $recInstance << 4;

                    $header = pack('vvV', $recVerInstance, $recType, $length);
                    $data .= $header . $optData;
                }

                // the client anchor
                if ($this->object->getStartCoordinates()) {
                    $recVer = 0x0;
                    $recInstance = 0x0;
                    $recType = 0xF010;

                    // start coordinates
                    [$column, $row] = Coordinate::indexesFromString($this->object->getStartCoordinates());
                    $c1 = $column - 1;
                    $r1 = $row - 1;

                    // start offsetX
                    $startOffsetX = $this->object->getStartOffsetX();

                    // start offsetY
                    $startOffsetY = $this->object->getStartOffsetY();

                    // end coordinates
                    [$column, $row] = Coordinate::indexesFromString($this->object->getEndCoordinates());
                    $c2 = $column - 1;
                    $r2 = $row - 1;

                    // end offsetX
                    $endOffsetX = $this->object->getEndOffsetX();

                    // end offsetY
                    $endOffsetY = $this->object->getEndOffsetY();

                    $clientAnchorData = pack('vvvvvvvvv', $this->object->getSpFlag(), $c1, $startOffsetX, $r1, $startOffsetY, $c2, $endOffsetX, $r2, $endOffsetY);

                    $length = strlen($clientAnchorData);

                    $recVerInstance = $recVer;
                    $recVerInstance |= $recInstance << 4;

                    $header = pack('vvV', $recVerInstance, $recType, $length);
                    $data .= $header . $clientAnchorData;
                }

                // the client data, just empty for now
                if (!$this->object->getSpgr()) {
                    $clientDataData = '';

                    $recVer = 0x0;
                    $recInstance = 0x0;
                    $recType = 0xF011;

                    $length = strlen($clientDataData);

                    $recVerInstance = $recVer;
                    $recVerInstance |= $recInstance << 4;

                    $header = pack('vvV', $recVerInstance, $recType, $length);
                    $data .= $header . $clientDataData;
                }

                // write the record
                $recVer = 0xF;
                $recInstance = 0x0000;
                $recType = 0xF004;
                $length = strlen($data);

                $recVerInstance = $recVer;
                $recVerInstance |= $recInstance << 4;

                $header = pack('vvV', $recVerInstance, $recType, $length);

                $this->data = $header . $data;

                break;
        }

        return $this->data;
    }

    /**
     * Gets the shape offsets.
     *
     * @return int[]
     */
    public function getSpOffsets(): array
    {
        return $this->spOffsets;
    }

    /**
     * Gets the shape types.
     *
     * @return mixed[]
     */
    public function getSpTypes(): array
    {
        return $this->spTypes;
    }
}
