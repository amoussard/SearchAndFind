<?php

namespace Drassuom\ImportBundle\Converter\ORM;

use Doctrine\ORM\EntityManager;

use Drassuom\ImportBundle\Converter\Converter;
use Drassuom\ImportBundle\Entity\Mapping;
use Drassuom\ImportBundle\Entity\MappingRepository;
use Drassuom\ImportBundle\Manager\RegistryManager;

/**
 * Description of BaseConverter.php
 *
 * @author: m.monsang <m.monsang@novactive.com>
 */
abstract class BaseORMConverter extends Converter
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var RegistryManager
     */
    protected $registry;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var array
     */
    protected $mapping;

    /**
     * @param \Doctrine\ORM\EntityManager $em
     * @param $registry
     * @InjectParams({
     *     "em"         = @Inject("doctrine.orm.entity_manager"),
     *     "registry"   = @Inject("nova_import.registry_manager")
     * })
     */
    public function __construct(EntityManager $em, RegistryManager $registry)
    {
        $this->em = $em;
        $this->registry = $registry;
        $this->prefetchMapping();
    }

    /**
     *
     */
    protected function prefetchMapping()
    {
        if (!$this->registry->has('prefetch', 'mapping')) {
            $this->mapping = array();
            /** @var MappingRepository $repo  */
            $repo = $this->em->getRepository('NovaImportBundle:Mapping');
            $mappingList = $repo->findAll();
            foreach ($mappingList as $mapping) {
                $key = $mapping->getType().'_'.$mapping->getOriginalValue();
                $this->mapping[$key] = $mapping;
            }
            $this->registry->add('prefetch', $this->mapping, 'mapping');
        } else {
            $this->mapping = $this->registry->get('prefetch', 'mapping');
        }
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository($this->class);
    }

    /**
     * {@inheritdoc}
     */
    public function convert($input, $options = array())
    {
        if (is_object($input)) {
            // object alredy converted
            return $input;
        }
        if (!empty($options['use_mapping'])) {
            $mapping = $this->getMappingFor($input, $options);
        } else {
            $mapping = null;
        }
        $key = $this->getKey($input, $mapping, $options);
        if ($key && $this->registry->has($key)) {
            $object = $this->registry->get($key);
            //$this->refreshEntity($object);
            return $object;
        }
        $output = $this->doConvert($input, $mapping, $options);
        if (is_object($output) && $key) {
            // keep object in registry, to avoid duplicate (when transaction not flushed)
            $this->registry->add($key, $output);
        }
        return $output;
    }


    /**
     * @abstract
     *
     * @param      $input
     * @param null $mapping
     * @param      $options
     *
     * @return
     */
    abstract public function doConvert($input, $mapping = null, $options);

    /**
     * @param $value
     * @param $options
     *
     * @return Mapping
     */
    protected function getMappingFor($value, $options) {
        $mappingName = isset($options['mapping_name']) ? $options['mapping_name'] : null;
        $key = $mappingName.'_'.$value;
        if (isset($this->mapping[$key])) {
            return $this->mapping[$key];
        }
        $key = $mappingName.'_'.Mapping::TYPE_ALL;
        if (isset($this->mapping[$key])) {
            return $this->mapping[$key];
        }
        return null;
    }

    /**
     * @param       $item
     * @param null  $mapping
     * @param array $options
     *
     * @return null|string
     */
    protected function getKey($item, $mapping = null, array &$options)
    {
        $key = (isset($options['entity']) ? $options['entity'] : $this->class) . '||';
        if ($mapping) {
            $key .= $mapping->getType() . '_' . $mapping->getRef();
        } else {
            if (is_array($item)) {
                foreach ($item as $it) {
                    $itemKey = $this->getItemKey($item);
                    if ($itemKey) {
                        $key .=  $itemKey . ';';
                    } else {
                        return null;
                    }
                }
            } else {
                $itemKey = $this->getItemKey($item);
                if ($itemKey) {
                    $key .=  $itemKey;
                } else {
                    return null;
                }
            }
        }
        return (sha1(strtolower($key)));
    }

    /**
     * @param $item
     * @return null|string
     */
    protected function getItemKey($item) {
        if (is_object($item)) {
            $uow = $this->em->getUnitOfWork();
            if ($uow->isInIdentityMap($item)) {
                $ids = $this->em->getUnitOfWork()->getEntityIdentifier($item);
                return implode('|', $ids);
            } else {
                if (method_exists($item, '__toString')) {
                    return (string)$item;
                } else {
                    return null;
                }
            }
        } elseif (is_array($item)){
            return implode('_', $item);
        }
        return $item;
    }

    /**
     * @param $object
     * @param $key
     */
    public function create($object, $key)
    {
        $this->registry->add($key, $object);
    }

    private function refreshEntity($entity) {
        $uow = $this->em->getUnitOfWork();
        $entityState = $uow->getEntityState($entity);
        if ($entityState == \Doctrine\ORM\UnitOfWork::STATE_DETACHED) {
            //$uow->getEntityIdentifier($object);
            $uow->registerManaged($entityState, array(), array());
            $uow->markReadOnly($entity);
        }
        $uow->refresh($entity);
    }
}