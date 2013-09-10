<?php

namespace Drassuom\ImportBundle\Converter;

/**
 * Converter class
 */
abstract class Converter
{
    /**
     * @var Callback
     */
    protected $warningCallback;

    /**
     * @param       $input
     * @param array $options
     *
     * @return mixed
     */
    abstract public function convert($input, $options = array());

    protected function warn($message, $params) {
        if (!$this->warningCallback) {
            return;
        }
        call_user_func_array($this->warningCallback, array($message, $params));
    }

    public function setWarningCallback($warningCallback)
    {
        if (!is_callable($warningCallback)) {
            throw new \InvalidArgumentException("Invalide callback [$this->warningCallback]. It's should be callable.");
        }
        $this->warningCallback = $warningCallback;
    }
}