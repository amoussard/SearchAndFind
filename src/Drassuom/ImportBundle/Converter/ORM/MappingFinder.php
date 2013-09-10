<?php

namespace Drassuom\ImportBundle\Converter\ORM;

/**
 * Return the mapped value for the input
 */
class MappingFinder extends BaseORMConverter
{

    /**
     * @param $input
     * @param null $mapping
     * @param $options
     */
    public function doConvert($input, $mapping = null, $options)
    {

    }
    /**
     * {@inheritdoc}
     */
    public function convert($input, $options = array())
    {
        if (empty($input)) {
            return '';
        }

        $mapping = $this->getMappingFor($input, $options);
        if ($mapping) {
            return $mapping->getRef();
        }
        return '';
    }
}