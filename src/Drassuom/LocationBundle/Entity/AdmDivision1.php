<?php

namespace Drassuom\LocationBundle\Entity;

use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations
use Doctrine\ORM\Mapping as ORM;

/**
 * AdmDivision1
 *
 * @ORM\Entity
 */
class AdmDivision1 extends Location
{
    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=20)
     */
    protected $code;

    /**
     * @param string $code
     */
    public function setCode($code) {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }
}
