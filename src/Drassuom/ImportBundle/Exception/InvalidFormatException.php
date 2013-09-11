<?php

namespace Nova\ImportBundle\Exception;

use Drassuom\ImportBundle\Exception\ImportException;

/**
 * Description of InvalidFormatException
 */
class InvalidFormatException extends ImportException
{
    /**
     * @var string
     */
    protected $nodeName;


    /**
     * @param string $nodeName
     */
    public function setNodeName($nodeName)
    {
        $this->nodeName = $nodeName;
    }

    /**
     * @return string
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }
}