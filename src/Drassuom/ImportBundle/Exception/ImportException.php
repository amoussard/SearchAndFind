<?php

namespace Drassuom\ImportBundle\Exception;

class ImportException extends \RuntimeException
{

    const LEVEL_WARNING = 1;
    const LEVEL_ERROR = 2;
    const LEVEL_CRITICAL = 3;

    public function __toString() {
        $str = '';
        if ($this->rowNumber) {
            $str = '[Ligne='.$this->rowNumber.'] : ';
        }
        if ($this->rowReference) {
            $str .= '[Référence='.$this->rowReference.'] : ';
        }
        $str .= $this->getMessage()."\n";
        return $str;
    }

    /**
     * @var int $rowNumber
     */
    protected $rowNumber;

    /**
     * @var string $rowReference
     */
    protected $rowReference;


    protected $level = self::LEVEL_ERROR;

    /**
     * @param int $rowNumber
     */
    public function setRowNumber($rowNumber)
    {
        $this->rowNumber = $rowNumber;
    }

    /**
     * @return int
     */
    public function getRowNumber()
    {
        return $this->rowNumber;
    }


    public function isFatal() {
        return ($this->level >= self::LEVEL_ERROR);
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }

    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param string $rowReference
     */
    public function setRowReference($rowReference)
    {
        $this->rowReference = $rowReference;
    }

    /**
     * @return string
     */
    public function getRowReference()
    {
        return $this->rowReference;
    }
}