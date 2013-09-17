<?php

namespace Drassuom\ImportBundle;

use Doctrine\ORM\EntityManager;
use Drassuom\ImportBundle\Manager\ProgressManager;
use Drassuom\ImportBundle\Writer\ORM\BaseWriter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\PropertyAccess\PropertyPath;

use Drassuom\ImportBundle\Exception\ConfigException;
use Drassuom\ImportBundle\Entity\Import;

class Workflow
{

    /**
     * @var array
     */
    protected $conditions = array();

    /**
     * @var array
     */
    protected $writers;

    /**
     * @var \Traversable
     */
    protected $reader;

    /**
     * @var object
     */
    protected $validator;

    /**
     * @var array
     */
    protected $converters = array();

    /**
     * @var array
     */
    protected $preValidators = array();

    /**
     * @var array
     */
    protected $postValidator = array();

    /**
     * @var array
     */
    protected $filters = array();

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var IdentityTranslator
     */
    protected $translator;

    /**
     * @var string
     */
    protected $refFields = array();

    /**
     * @var array
     */
    protected $conditionalMappingOptions = array();

    /**
     * @var array
     */
    protected $mappingOptions = array();

    /**
     * @var Import
     */
    protected $import;

    /**
     * @var int
     */
    protected $currentRow = 0;

    /**
     * @var int
     */
    protected $countRow = 0;

    /**
     * @var array
     */
    protected $currentItem;

    /**
     * @var array|null
     */
    protected $childrenOptions = null;

    /**
     * @var \Symfony\Component\HttpKernel\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output = null;

    /**
     * @var bool $stop
     */
    protected $stop = false;

    /**
     * @var ProgressManager
     */
    protected $progressManager;

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container
     * @param IdentityTranslator        $translator
     */
    public function __construct(Container $container, IdentityTranslator $translator) {
        $this->container = $container;
        $this->writers = array();
        $this->translator = $translator;
    }


    public function process(Import $import)
    {
        $this->prepare($import);
        // Read all items
        $this->countRow = $this->reader->count();
        $this->progressManager->setupProgress($this, $this->countRow);
        foreach ($this->reader as $item) {
            $this->currentRow++;
            $this->processItem($import, $item);
            $this->progressManager->showProgress($this->currentRow);
            if ($this->stop) {
                if ($this->output) {
                    $this->output->writeln("\n<comment>Someone ask me to stop</comment>. Current row is <info>".$this->currentRow.'</info>');
                }
                break;
            }
        }
        $this->finish($import);
        $this->progressManager->clearProgress();
        return $this->currentRow;
    }


    /**
     * @param Entity\Import $import
     * @param bool          $isChild
     */
    public function prepare(Import $import, $isChild = false) {
        $this->currentRow = 0;
        $this->import = $import;

        // Prepare writers
        foreach ($this->writers as $writer) {
            $writer->prepare($isChild);
        }
    }

    /**
     * @param Import $oImport
     * @param bool   $isChild
     */
    public function finish(Import $oImport, $isChild = false) {
        // Finish writers
        foreach ($this->writers as $writer) {
            $writer->finish($isChild);
        }


    }

    /**
     * @param Import $oImport
     * @param        $item
     * @param null   $parent
     *
     * @return bool
     */
    public function processItem(Import $oImport, $item, $parent = null) {
        // first off all, check conditions
        $conditions = $this->checkConditions($item);
        $mappingErrors = array();
        $mappingOptions = $this->mappingOptions;
        foreach ($conditions as $condition) {
            if (isset($this->conditionalMappingOptions[$condition])) {
                $mappingOptions = array_merge($mappingOptions, $this->conditionalMappingOptions[$condition]);
            }
        }

        if ($this->filterRow($conditions)) {
            $oImport->setRowsSkipped($oImport->getRowsSkipped() + 1);
            return false;
        }

        $mappedItem = $this->mapper->map($item, $mappingOptions, $mappingErrors);
        $this->currentItem = $mappedItem;
        if (count($mappingErrors) > 0) {
            $this->addErrors($oImport, $mappingErrors);
            return false;
        }

        // prevalidation (before conversion)
        $validationErrors = array();
        $preValidationsOptions = $this->filterByCondition($this->preValidators, $conditions, 1);
        if (!$this->validator->validate($mappedItem, $mappedItem, $preValidationsOptions, $validationErrors)) {
            $this->addErrors($oImport, $validationErrors);
            return false;
        }

        $conversionOptions = $this->filterByCondition($this->converters, $conditions, 1);
        $convertedItems = $this->convertItems($mappedItem, $conversionOptions);

        // postvalidation (without condition)
        $validationErrors = array();
        $postValidationsOptions = $this->filterByCondition($this->postValidators, $conditions, 1);
        if (!$this->validator->validate($convertedItems, $mappedItem, $postValidationsOptions, $validationErrors)) {
            $this->addErrors($oImport, $validationErrors);
            return false;
        }


        foreach ($this->refFields as $fieldName) {
            if (empty($convertedItems[$fieldName])) {
                $error = array('error.empty.ref_field_name' => array());
                $this->addErrors($oImport, $error);
                return false;
            }
        }

        $object = null;
        /** @var BaseWriter $oWriter */
        foreach ($this->writers as $oWriter) {
            $object = $oWriter->writeItem($convertedItems, $mappedItem, $parent);
        }

        if ($this->childrenOptions) {
            $childrenPath = $this->childrenOptions['path'];
            /** @var Workflow $childrenWorkflow */
            $childrenWorkflow = $this->childrenOptions['workflow'];

            $childrenWorkflow->prepare($oImport, true);

            foreach ($this->mapper->getChildren($item, $childrenPath) as $childItem) {
                if (!$childrenWorkflow->processItem($oImport, $childItem, $object)) {
                    return false;
                }
            }

            $childrenWorkflow->finish($oImport, true);
        }
        return true;
    }

    /**
     * @param array $conditions
     *
     * @return bool
     */
    protected function filterRow(array &$conditions) {
        if (!empty($this->filters['exclude'])) {
            $toExclude  = $this->filters['exclude'];
            if (!is_array($toExclude)) {
                $toExclude = array($toExclude);
            }

            foreach ($toExclude as $exclude) {
                if (in_array($exclude, $conditions)) {
                    return true;
                }
            }
        }
        if (!empty($this->filters['include'])) {
            $toInclude  = $this->filters['include'];
            if (!is_array($toInclude)) {
                $toInclude = array($toInclude);
            }

            $filledCondition = false;
            foreach ($toInclude as $include) {
                if (in_array($include, $conditions)) {
                    $filledCondition = true;
                    break;
                }
            }
            return !$filledCondition;
        }
        return false;
    }


    /**
     * @param Entity\Import $oImport
     * @param array         $messages
     */
    public function addErrors(Import $oImport, array &$messages) {
        foreach ($messages as $key => $params) {
            if (!is_array($params)) {
                $params = array();
            }
            $error = $this->fillMessage($key, $params);
            $oImport->addError($error);
        }
        $oImport->setNbErrors($oImport->getNbErrors() + 1);
    }

    /**
     * @param Entity\Import $import
     * @param               $message
     * @param               $params
     */
    public function addWarning(Import $import, $message, $params) {
        $message = $this->fillMessage($message, $params);
        $import->addWarning($message);
    }

    /**
     * @param $message
     * @param $params
     * @return string
     */
    protected function fillMessage($message, $params) {
        $translatedMessage = $this->translator->trans($message, $params, 'import');

        $ref = $this->getItemReference($this->currentItem);
        $params = array(
            '{{ message }}' => $translatedMessage,
            '{{ row }}'     => $this->currentRow + 1,
            '{{ ref }}'     => $ref,

        );
        return $this->translator->trans('import_error_format', $params, 'import');
    }

    /**
     * @param $item
     * @return string
     */
    protected function getItemReference(array &$item) {
        $ref = array();
        foreach ($this->refFields as $fieldName) {
            $ref []= isset($item[$fieldName]) ? $item[$fieldName] : '';
        }
        return implode('|', $ref);
    }

    /**
     * @param $item
     * @return array
     * @throws \RuntimeException
     */
    protected function checkConditions($item) {
        $ret = array();
        $mappingError = array();
        foreach ($this->conditions as $name => $options) {
            $key = $options['key'];
            if (!isset($this->mappingOptions[$key])) {
                throw new \RuntimeException("can not get mapping options for [$key]");
            }
            $propertyPath = self::getFixedPropertyPath($key);
            $mappingOptions = $this->mappingOptions[$key];
            $propertyPath->setValue($item, $this->mapper->mapItem($item, $key, $mappingOptions, $mappingError));
            if (isset($this->converters[$key])) {
                $convertOptions = $this->converters[$key];
                $convertedItem = $this->convertItem($item, $key, $convertOptions);
            } else {
                $convertedItem = $propertyPath->getValue($item);
            }

            if ($this->validator->validateItem($convertedItem, $options['call'], $options)) {
                $ret[] = $name;
            } else {
                $ret[] = "!$name";
            }
        }
        return $ret;
    }

    /**
     * @param array $items
     * @param array $conditions
     * @param int   $level
     *
     * @return array
     */
    protected function filterByCondition(array $items, array &$conditions, $level = 0) {
        $ret = array();
        if ($level > 0) {
            foreach ($items as $key => $value) {
                $ret[$key] = $this->filterByCondition($value, $conditions, --$level);
            }
        } else {
            foreach ($items as $key => $value) {
                if (!isset($value['condition']) && !isset($value['conditions'])) {
                    $ret[$key] = $value;
                } elseif (isset($value['condition']) && in_array($value['condition'], $conditions)) {
                    $ret[$key] = $value;
                } elseif (isset($value['conditions']) && is_array($value['conditions'])) {
                    $fillCondition = true;
                    foreach ($value['conditions'] as $search) {
                        if (!in_array($search, $conditions)) {
                            $fillCondition = false;
                            break;
                        }
                    }
                    if ($fillCondition) {
                        $ret[$key] = $value;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Call a function from information found in $callOptions
     *
     * @param       $item
     * @param       $call
     * @param array $callOptions
     *
     * @throws ConfigException
     * @return mixed
     */
    private function call($item, $call, array $callOptions) {
        if (!empty($callOptions['params'])) {
            $params = $callOptions['params'];
        } else {
            $params = null;
        }

        if (function_exists($call)) {
            $callback = $call;
            $callParams = array($item);
        }  elseif (class_exists($call)) {
            $method = $callOptions['method'];
            if (!method_exists($call, $method)) {
                throw new ConfigException("method [$method] doesn't exists into [$call]");
            }
            $callback = array($call, $method);
            $callParams = array($item);
        } else {
            if (!$this->container->has($call)) {
                throw new ConfigException("unknown service [$call]");
            }
            $service = $this->container->get($call);
            if (method_exists($service, "setWarningCallback")) {
                $object = $this;
                $import = $this->import;
                $warningCallback = function ($message, $params) use ($object, $import){
                    $object->addWarning($import, $message, $params);
                };
                $service->setWarningCallback($warningCallback);
            }
            $method = $callOptions['method'];
            if (!method_exists($service, $method)) {
                throw new ConfigException("method [$method] doesn't exists into [$call]");
            }
            $callback = array($service, $method);
            $callParams = array($item);
        }

        if ($params) {
            if ($callOptions['merge']) {
                $callParams = array_merge($callParams, $params);
            } else {
                $callParams[] = $params;
            }
        }

        $ret = call_user_func_array($callback, $callParams);
        return $ret;
    }

    /**
     * @param array $items
     * @param       $key
     * @param array $convertOptions
     *
     * @return mixed
     */
    protected function convertItem(array $items, $key, array &$convertOptions) {
        $convertedItem = isset($items[$key]) ? $items[$key] : '';
        foreach ($convertOptions as $call => $value) {
            if (isset($value['params']['import'])) {
                $value['params']['import'] = $this->import;
            }
            if (!empty($value['value'])) {
                $convertedItem = $value['value'];
            } else {
                if (!empty($value['field'])) {
                    $propertyPath = self::getFixedPropertyPath($value['field']);
                    $convertedItem = $propertyPath->getValue($items);
                } elseif (isset($value['fields']) && count($value['fields']) > 0) {
                    $fields = $value['fields'];
                    $convertedItem = array();
                    foreach ($fields as $key => $field) {
                        $propertyPath = self::getFixedPropertyPath($field);
                        if (is_numeric($key)) {
                            $convertedItem[$field] = $propertyPath->getValue($items);;
                        } else {
                            $convertedItem[$key] = $propertyPath->getValue($items);
                        }
                    }
                }
            }
            if (empty($convertedItem)) {
                break;
            }
            if (is_array($convertedItem) && empty($value['fields'])) {
                $ret = array();
                foreach ($convertedItem as $itemKey => $itemValue) {
                    $ret[$itemKey] = $this->call($itemValue, $call, $value);
                }
                $convertedItem = $ret;
            } else {
                $convertedItem = $this->call($convertedItem, $call, $value);
            }
        }
        return $convertedItem;
    }

    /**
     * @param       $item
     * @param array $converters
     *
     * @return mixed
     */
    private function convertItems($item, array &$converters) {
        foreach ($converters as $key => $options) {
            if (count($options) > 0){
                $propertyPath = self::getFixedPropertyPath($key);
                $converted = $this->convertItem($item, $key, $options);
                if (!empty($converted)) {
                    $propertyPath->setValue($item, $converted);
                }
            }
        }
        return $item;
    }

    /**
     * @param $accessor
     *
     * @return PropertyPath
     */
    public static function getFixedPropertyPath($accessor) {
        $fixedAccessor = preg_replace('/^([^\[]+)([\[]?)/', '[$1]$2', $accessor, 1);
        return new PropertyPath($fixedAccessor);
    }

    /**
     * @param object $validator
     */
    public function setValidator($validator) {
        $this->validator = $validator;
    }

    /**
     * @return object
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * @param $reader
     */
    public function setReader($reader)
    {
        $this->reader = $reader;
    }

    /**
     * @return Traversable
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * @param array $conditions
     */
    public function setConditions($conditions)
    {
        $this->conditions = $conditions;
    }

    /**
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * @param array $converters
     */
    public function setConverters($converters)
    {
        $this->converters = $converters;
    }

    /**
     * @return array
     */
    public function getConverters()
    {
        return $this->converters;
    }

    /**
     * @param $mapper
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @return \Drassuom\ImportBundle\Mapper $mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param array $mappingOptions
     */
    public function setMappingOptions($mappingOptions) {
        $this->mappingOptions = $mappingOptions;
    }

    /**
     * @return array
     */
    public function getMappingOptions()
    {
        return $this->mappingOptions;
    }

    /**
     * @param $postValidators
     *
     * @internal param array $postValidator
     */
    public function setPostValidators($postValidators)
    {
        $this->postValidators = $postValidators;
    }

    /**
     * @return array
     */
    public function getPostValidators()
    {
        return $this->postValidators;
    }

    /**
     * @param array $preValidators
     */
    public function setPreValidators($preValidators)
    {
        $this->preValidators = $preValidators;
    }

    /**
     * @return array
     */
    public function getPreValidators()
    {
        return $this->preValidators;
    }

    /**
     * @param $writer
     */
    public function addWriter($writer)
    {
        $this->writers[] = $writer;
    }

    /**
     * @return array
     */
    public function getWriters()
    {
        return $this->writers;
    }

    /**
     * @param $refFields
     */
    public function setRefFields($refFields) {
        $this->refFields = $refFields;
    }

    /**
     * @return string
     */
    public function getRefFields()
    {
        return $this->refFields;
    }

    /**
     * @param \Symfony\Component\Translation\Translator $translator
     */
    public function setTranslator($translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return \Symfony\Component\Translation\Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * @param array $conditionalMappingOptions
     */
    public function setConditionalMappingOptions($conditionalMappingOptions)
    {
        $this->conditionalMappingOptions = $conditionalMappingOptions;
    }

    /**
     * @param array|null $childrenOptions
     */
    public function setChildrenOptions($childrenOptions) {
        $this->childrenOptions = $childrenOptions;
    }

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * @param boolean $stop
     */
    public function setStop($stop)
    {
        $this->stop = $stop;
    }

    /**
     * @param ProgressManager $progressManager
     */
    public function setProgressManager(ProgressManager $progressManager)
    {
        $this->progressManager = $progressManager;
    }

    /**
     * @return int
     */
    public function getCurrentRow()
    {
        return $this->currentRow;
    }
}