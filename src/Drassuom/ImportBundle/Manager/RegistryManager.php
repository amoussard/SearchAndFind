<?php

namespace Drassuom\ImportBundle\Manager;

/**
 * RegistryManager: Remember newly created object
 */
class RegistryManager
{

    /**
     * @var array
     */
    protected $registry = array();

    private function loadContext($sContext) {
        if (!isset($this->registry[$sContext])) {
            $this->registry[$sContext] = array();
        }
        return $this->registry[$sContext];
    }

    /**
     * @param        $index
     * @param string $sContext
     *
     * @return mixed
     */
    public function get($index, $sContext = 'object') {
        $this->loadContext($sContext);
        if (isset($this->registry[$sContext][$index])) {
            return $this->registry[$sContext][$index];
        }
        return null;
    }

    /**
     * @param        $index
     * @param string $sContext
     *
     * @return bool
     */
    public function has($index, $sContext = 'object') {
        $this->loadContext($sContext);
        return isset($this->registry[$sContext][$index]);
    }

    /**
     * @param        $index
     * @param        $value
     * @param string $sContext
     */
    public function add($index, $value, $sContext = 'object') {
        $this->loadContext($sContext);
        $this->registry[$sContext][$index] = $value;
    }


    /**
     * Clear registry
     *
     * @param string $sContext
     */
    public function clear($sContext = 'object') {
        $tab = $this->loadContext($sContext);
        foreach ($tab as $key => $value) {
            unset($value);
        }
        unset($this->registry[$sContext]);
    }

}