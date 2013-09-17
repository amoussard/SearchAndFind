<?php

namespace Drassuom\LocationBundle\Entity;

use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations
use Doctrine\ORM\Mapping as ORM;

/**
 * City
 *
 * @ORM\Entity
 */
class Continent extends Location
{
    /**
     * @var string
     *
     * @ORM\Column(name="iso", type="string", length=2)
     */
    protected $iso;

    /**
     * @param string $iso
     */
    public function setIso($iso) {
        $this->iso = $iso;
    }

    /**
     * @return string
     */
    public function getIso() {
        return $this->iso;
    }


}
