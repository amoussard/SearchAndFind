<?php

namespace Drassuom\LocationBundle\Import\Converter;

use Doctrine\Common\Collections\ArrayCollection;
use Drassuom\ImportBundle\Converter\ORM\BaseORMConverter;
use Drassuom\LocationBundle\Entity\Country;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("drassuom_location.converter.list")
 */
class ListConverter extends BaseORMConverter
{
    protected $class = null;

    /**
     * @param array $aInput
     * @param null  $mapping
     * @param array $aOptions
     *
     * @return ArrayCollection
     */
    public function doConvert($aInput, $mapping = null, $aOptions) {
        $sRefField = "id";
        if (isset($aOptions["refField"])) {
            $sRefField = $aOptions["refField"];
        }
        if (isset($aOptions["entity"])) {
            $this->class = $aOptions["entity"];
        }
        $sDelimiter = ",";
        if (isset($aOptions["delimiter"])) {
            $sDelimiter = $aOptions["delimiter"];
        }
        /** @var string[] $aElements */
        $aElements = explode($sDelimiter, $aInput);

        $oRepo = $this->getRepository();

        $aList = new ArrayCollection();
        foreach ($aElements as $sElementIdentifier) {
            $aCriteria = array(
                $sRefField => $sElementIdentifier
            );
            /** @var Country $oCountry */
            $oCountry = $oRepo->findOneBy($aCriteria);

            if ($oCountry) {
                $aList->add($oCountry);
//                $aCriteria = array(
//                    '' => $oCountry->getIso()
//                );
//                /** @var Country $oCountry */
//                $oCountry = $oRepo->findOneBy($aCriteria);
//                var_dump($oCountry->getName());
            }
        }
        return $aList;
    }
}