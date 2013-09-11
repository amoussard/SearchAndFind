<?php

namespace Nova\ImportBundle\Mapper;

use Drassuom\ImportBundle\Workflow;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Map array item
 * @DI\Service("drassuom_import.mapper.default")
 */
class ArrayMapper
{
    /**
     * @param \SimpleXMLElement $item
     * @param array $mapppingOptions
     * @param array $mappingError
     * @return array
     * @throws \RuntimeException
     */
    public function map($item, array &$mapppingOptions, array &$mappingError) {
        $tab = array();
        $maxPath = 0;
        foreach ($mapppingOptions as $key => $path) {
            $maxPath = max($maxPath, $path['path']);
        }
        $count = count($item);
        if ($count < $maxPath) {
            $mappingError['error.mapping.min_column'] = array('{{ count }}' => $count, '{{ min }}' => $maxPath);
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($mapppingOptions as $key => $path) {
            $propertyPath = Workflow::getFixedPropertyPath($key);
            $propertyAccessor->setValue($tab, $propertyPath, $this->mapItem($item, $key, $path, $mappingError));
        }
        return $tab;
    }

    /**
     * @param \SimpleXmlElement $item
     * @param $key
     * @param $path
     * @param array $mappingError
     * @return array|null|string
     * @throws \RuntimeException
     */
    public function mapItem($item, $key, $path, array &$mappingError) {
        if (is_array($path)) {
            $path = $path['path'];
        }

        if (!isset($item[$path])) {
            return null;
        }

        $valueNode = $item[$path];
        return $valueNode;
    }
}