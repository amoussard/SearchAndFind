<?php

namespace Drassuom\ImportBundle\Validator;

use Symfony\Component\DependencyInjection\Container;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * Description of Validator.php
 * @DI\Service("drassuom_import.validator.default")
 */
class Validator
{

    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container
     *
     * @DI\InjectParams({
     *     "container" = @DI\Inject("service_container")
     * })
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * @param array $item
     * @param array $originalItem
     * @param       $options
     * @param array $validationErrors
     *
     * @return bool
     */
    public function validate($item, array &$originalItem, $options, array &$validationErrors) {
        $hasError = false;
        foreach ($options as $key => $tab) {
            foreach ($tab as $call => $fieldOptions) {
                $message = $fieldOptions['message'];
                if (!empty($fieldOptions['fields'])) {
                    $newItem = array();
                    foreach ($fieldOptions['fields'] as $key => $field) {
                        $newItem[$key] = $item[$field];
                    }
                } else {
                    $newItem = isset($item[$key]) ? $item[$key] : null;
                }
                $validationExtra = array();
                if (!$this->validateItem($newItem, $call, $fieldOptions, $validationExtra)) {
                    $hasError = true;
                    $originalItemValue = isset($originalItem[$key]) ? $originalItem[$key] : '';
                    $messageParam = array('{{ value }}' => $originalItemValue, '{{ params }}' => implode(',', $fieldOptions['params']));
                    if (!empty($validationExtra)) {
                        $messageParam = array_merge($messageParam, $validationExtra);
                    }
                    $validationErrors[$message] = $messageParam;
                }
            }
        }
        if ($hasError) {
            return false;
        }
        return true;
    }

    /**
     * @param       $item
     * @param       $call
     * @param       $options
     * @param array $validationErrorsParam
     *
     * @return bool
     */
    public function validateItem($item, $call, $options, array &$validationErrorsParam = null) {
        if (!empty($options['trim'])) {
            $item = trim($item);
        }
        if (function_exists($call)) {
            $callback =  $call;
            $params = array($item);
        } elseif (class_exists($call)) {
            $method = $options['method'];
            $callback = array($call, $method);
            $params = array($item);
        } elseif (method_exists($item, $call)) {
            $callback =  array($item, $call);
            $params = array();
        } elseif ($this->container->has($call)) {
            $service = $this->container->get($call);
            $callback = array($service, 'validate');
            $params = array($item);
        } else {
            return false;
        }
        $nullable = isset($options['nullable']) ? $options['nullable'] : false;
        $requiredValue = isset($options['required_value']) ? $options['required_value'] : true;
        if (!empty($item) || !$nullable) {
            if (!empty($options['params'])) {
                if (!empty($options['merge'])) {
                    $params = array_merge($params, $options['params']);
                } else {
                    $params[] = $options['params'];
                }
            }
            $ret = call_user_func_array($callback, $params);
            if ($ret !== $requiredValue) {
                if (!empty($options['allow_add_extra']) && $validationErrorsParam !== null && is_array($ret)) {
                    // add extra message parameters
                    $validationErrorsParam = array_merge($validationErrorsParam, $ret);
                }
                return false;
            }
        }
        return true;
    }
}