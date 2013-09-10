<?php

namespace Drassuom\ImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Drassuom\ImportBundle\Entity\Mapping
 *
 * @ORM\Table(
 *      name="import_mapping",
 *      uniqueConstraints={@ORM\UniqueConstraint(
 *          name="mapping_uq",
 *          columns={"type", "original_value"}
 *      )}
 * )
 * @ORM\Entity(repositoryClass="Drassuom\ImportBundle\Repository\MappingRepository")
 */
class Mapping
{
    const TYPE_ALL = 'all';

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string $type
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @var string $originalValue
     *
     * @ORM\Column(name="original_value", type="string", length=255)
     */
    private $originalValue;

    /**
     * @var string $ref
     *
     * @ORM\Column(name="ref", type="string", length=255)
     */
    private $ref;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set originalValue
     *
     * @param string $originalValue
     */
    public function setOriginalValue($originalValue)
    {
        $this->originalValue = $originalValue;
    }

    /**
     * Get originalValue
     *
     * @return string
     */
    public function getOriginalValue()
    {
        return $this->originalValue;
    }

    /**
     * @param string $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @return string
     */
    public function getRef()
    {
        return $this->ref;
    }

}