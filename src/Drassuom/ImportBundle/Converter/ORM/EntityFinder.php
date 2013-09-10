<?php

namespace Drassuom\ImportBundle\Converter\ORM;

/**
 * Convert an input value to custom Object
 */
class EntityFinder extends BaseORMConverter
{

    /**
     * @param      $input
     * @param null $mapping
     * @param      $options
     *
     * @return mixed
     */
    public function doConvert($input, $mapping = null, $options)
    {
        if (!$input) {
            return;
        }
        $entity = $options['entity'];
        $useMapping = !empty($options['use_mapping']);
        if (is_array($input)) {
            $criteria = array();
            reset($input);
            foreach ($input as $key => $value) {
                if (is_object($value) && $this->em->getUnitOfWork()->isInIdentityMap($value)) {
                    $criteria[$key] = implode('~', $this->em->getUnitOfWork()->getEntityIdentifier($value));
                } else {
                    $criteria[$key] = $value;
                }
            }
        } else {
            if ($useMapping && $mapping) {
                $mappingField = (isset($options['mapping_field'])) ? $options['mapping_field'] : $options['field'];
                $criteria = array(
                    $mappingField => $mapping->getRef(),
                );
            } else {
                $criteria = array(
                    $options['field'] => $input,
                );
            }
        }

        $repo = $this->em->getRepository($entity);
        return $repo->findOneBy($criteria);
    }
}