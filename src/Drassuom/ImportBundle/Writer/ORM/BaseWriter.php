<?php

namespace Drassuom\ImportBundle\Writer\ORM;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bridge\Monolog\Logger;
use JMS\DiExtraBundle\Annotation as DI;

use Drassuom\ImportBundle\Entity\Import;
use Drassuom\ImportBundle\Manager\RegistryManager;

/**
 * Write an item into db
 *
 * @author: m.monsang <m.monsang@novactive.com>
 */
abstract class BaseWriter
{

    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_ERROR = 3;

    const BATCH_SIZE = 20;
    const BATCH_CLEAR_SIZE = 20;

    /**
     * @var string
     */
    protected $name = 'import';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var RegistryManager
     */
    protected $registry;

    /**
     * @var Import $import
     */
    protected $import;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var \Symfony\Component\HttpKernel\Log\LoggerInterface
     */
    protected $logger;

    /*
     * @var array
     */
    protected $currentItem;

    /**
     * @var string
     */
    protected $refField = array('remoteId');

    /**
     * @var bool
     */
    protected $options = array(
        'stopOnError'           => true,
        'stopOnFormatError'     => true,
        'transactionnal'        => false,
        'rollback'              => false,
        'dry-run'               => false,
        'verbose'               => false,
        'do_update'             => true,
        'do_insert'             => true,
    );

    /**
     * @param \Doctrine\ORM\EntityManager $em
     * @param RegistryManager $registry
     * @DI\InjectParams({
     *     "em"         = @DI\Inject("doctrine.orm.entity_manager"),
     *     "registry"   = @DI\Inject("drassuom_import.registry_manager")
     * })
     */
    public function __construct(EntityManager $em, RegistryManager $registry) {
        $this->em = $em;
        $this->registry = $registry;
    }

    /**
     * @abstract
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getRepository() {
        $class = $this->getOption('class');

        if ($class) {
            return $this->em->getRepository($class);
        }
        return null;
    }

    /**
     * @abstract
     *
     * @param       $object
     * @param array $item
     */
    protected function prePersist($object, array &$item) {

    }

    /**
     * @abstract
     *
     * @param       $object
     * @param array $item
     */
    protected function postPersist($object, array &$item) {

    }

    /**
     * @abstract
     *
     * @param       $object
     * @param array $item
     */
    protected function preUpdate($object, array &$item) {

    }

    /**
     * @abstract
     *
     * @param       $object
     * @param array $item
     */
    protected function postUpdate($object, array &$item) {

    }

    /**
     * @param Import $import
     */
    public function setCurrentImport(Import $import) {
        $this->import = $import;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Log\LoggerInterface $logger
     */
    public function setLogger(\Symfony\Component\HttpKernel\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * @return Import
     */
    public function getCurrentImport() {
        return $this->import;
    }

    /**
     * Prepare import
     */
    public function prepare($isChild = false) {
        if (!$isChild) {
            $this->import->setBeginTime(microtime(true));
            if ($this->getOption('transactionnal')) {
                $this->em->getConnection()->beginTransaction();
            }
        }
    }

    /**
     * Call at the end of the import, on success
     */
    public function finish($isChild = false) {
        if (!$isChild) {
            if (!$this->getOption('dry-run')) {
                $this->em->flush();
            }
            $this->import->setSuccess(true);
            if ($this->getOption('transactionnal')) {
                $this->em->getConnection()->commit();
            }
        }
    }

    /**
     * Call at the end of the import, if any error occured
     */
    public function onError() {
        $this->import->setSuccess(false);
        $this->fillImportData($this->import);

        if ($this->getOption('transactionnal') && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
        }
    }


    /**
     * Set writer options
     * @param array $options
     */
    public function setOptions(array $options) {
        if (isset($options['output'])) {
            $this->setOutput($options['output']);
        }
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @param array $item
     * @return bool
     */
    protected function doInsert(array &$item) {
        return $this->getOption('do_insert');
    }

    /**
     * @param       $object
     * @param array $item
     *
     * @return bool
     */
    protected function doUpdate($object, array &$item)
    {
        return $this->getOption('do_update');
    }

    /**
     * Do insert
     *
     * @param array $item
     * @param array $originalItem
     * @param null  $parent
     *
     * @return bool
     */
    protected function insert(array &$item, array &$originalItem, $parent = null) {
        if (!$this->doInsert($item)) {
            $this->log('Do not insert', true, self::LEVEL_DEBUG);
            return false;
        }
        $class = $this->getClass($item);
        $baseObject = new $class();
        $this->autofillObjects($baseObject, $item, $originalItem, $parent);
        $this->prePersist($baseObject, $item);
        $this->em->persist($baseObject);
        $this->postPersist($baseObject, $item);
        return $baseObject;
    }


    /**
     * @param $object
     */
    public function beforeFill($object) {

    }

    /**
     * Do update
     *
     * @param       $object
     * @param array $item
     * @param array $originalItem
     * @param null  $parent
     *
     * @return bool
     */
    public function update($object, array &$item, array &$originalItem, $parent = null) {
        if (!$this->doUpdate($object, $item)) {
            $this->log('Do not update', true, self::LEVEL_DEBUG);
            return false;
        }
        $this->autofillObjects($object, $item, $originalItem, $parent);
        $this->preUpdate($object, $item);
        $this->em->persist($object);
        $this->postUpdate($object, $item);
        return true;
    }

    /**
     * Do extra conversion here
     * @param array $item
     * @param array $originalItem
     */
    protected function doExtraConversion(array &$item, array &$originalItem) {
    }


    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item, array $originalItem = array(), $parent = null) {
        $object = null;
        $this->currentItem = $item;
        $this->doExtraConversion($item, $originalItem);
        if (($object = $this->alreadyExist($item))) {
            if (is_object($object) && $this->update($object, $item, $originalItem, $parent)) {
                $this->onUpdateSuccess($item, $object);
            } else {
                $this->onSkip($item, $object);
            }
        } else {
            if (($object = $this->insert($item, $originalItem, $parent))) {
                $this->onInsertSuccess($item, $object);
            } else {
                $this->onSkip($item, $object);
            }
        }
        return $object;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    protected function findRelatedObjects(array &$item) {
        $meta = $this->em->getClassMetadata($this->getClass($item));

        $mapping = $meta->getAssociationMappings();
        return $mapping;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    protected function getClassShortName(array &$item) {
        $meta = $this->em->getClassMetadata($this->getClass($item));

        return $meta->getReflectionClass()->getShortName();
    }

    /**
     * @param $baseObject
     * @param array $item
     * @param $parent
     */
    protected function setParent($baseObject, array &$item, $parent) {

    }

    /**
     * @param       $baseObject
     * @param array $item
     * @param array $originalItem
     * @param null  $parent
     *
     * @internal param array $objectList
     */
    protected function autofillObjects($baseObject, array &$item, array &$originalItem, $parent = null) {
        $called = array();
        $relatedObject = $this->findRelatedObjects($item);
        $objectList = array();
        $classShortName = $this->getClassShortName($item);
        if ($parent) {
            foreach ($relatedObject as $key => $value) {
                if ($parent instanceof $value['targetEntity']) {
                    $item[$key] = $parent;
                }
            }
        }
        foreach ($item as $key => $value) {
            $object = null;
            $fieldScope = $this->getFieldScope($key);
            if ($fieldScope) {
                // check if we can found the relation between the two classes
                if (array_key_exists($fieldScope, $relatedObject)) {
                    if (!isset($objectList[$fieldScope])) {
                        $getter = 'get'.ucfirst($fieldScope);
                        $object = $baseObject->$getter();
                        if (!$object) {
                            $objectClass = is_array($relatedObject[$fieldScope]) ? $relatedObject[$fieldScope]['targetEntity'] : $relatedObject[$fieldScope];
                            $object = new $objectClass();
                            $method = 'set'.ucfirst($fieldScope);
                            $baseObject->$method($object);
                        }
                        $objectList[$fieldScope] = $object;
                    } else {
                        $object = $objectList[$fieldScope];
                    }
                } else {
                    if (strcasecmp($fieldScope, $classShortName) !== 0) {
                        $this->log("Unknown relation [$fieldScope] for class [$classShortName]", true, self::LEVEL_DEBUG);
                        continue;
                    }
                }
            }
            if (!$object) {
                $object = $baseObject;
            }
            $fieldName = $this->getFieldName($key);
            $methodName = 'set'.ucfirst($fieldName);
            $called[get_class($object).':'.$fieldName] = $value;
            if (method_exists($object, $methodName) && $object !== null) {
                $object->$methodName($value);
            } else {
                $class = get_class($object);
                $this->log("Unknown method [$methodName] for class [$class]", true, self::LEVEL_DEBUG);
            }
        }
        $this->printAutofilledData($called);
    }

    /**
     * Debug: affiche les valeurs remplies automatiquement
     * @param array $called
     */
    private function printAutofilledData(array &$called) {
        // for debug only; show what should be inserted
        $logFilledObject = "Import autofilled data:\n";
        foreach ($called as $key => $value) {
            if ($value instanceof \DateTime) {
                $value = $value->format('c');
            } elseif (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_object($value)) {
                $tmp = get_class($value);
                if (method_exists($value, '__toString')) {
                    $tmp .= ":$value";
                }
                $value = $tmp;
            } elseif (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            }
            $logFilledObject .= "$key => <comment>$value</comment>\n";
        }
        $this->log($logFilledObject, true, self::LEVEL_DEBUG);
    }

    /**
     * Call on each row, after
     *
     * @param array $item
     * @param       $object
     */
    protected function onInsertSuccess(array &$item, $object) {
        $key = $this->getItemKey($item);
        if ($key) {
            $this->registry->add($key, $object);
        }
        if (method_exists($object, '__toString')) {
            $this->log("Object <comment>$object updated</comment>", true, self::LEVEL_DEBUG);
        }
        $nbRowsInserted = $this->import->getRowsInserted() + 1;
        $this->import->setRowsInserted($nbRowsInserted);
        $this->flush();
    }

    /**
     * Call on each row, after skiped object
     * @param array $item
     * @param $object
     */
    protected function onSkip(array &$item, $object) {
        if (method_exists($object, '__toString')) {
            $this->log("Object <comment>$object skipped</comment>", true, self::LEVEL_DEBUG);
        }
        $this->import->setRowsSkipped($this->import->getRowsSkipped() + 1);
    }

    /**
     * Call on each row, after update success
     *
     * @param array $item
     * @param       $object
     */
    protected function onUpdateSuccess(array &$item, $object) {
        $nbRowsUpdated = $this->import->getRowsUpdated() + 1;
        $this->import->setRowsUpdated($nbRowsUpdated);
        if (method_exists($object, '__toString')) {
            $this->log("Object <comment>$object updated</comment>", true, self::LEVEL_DEBUG);
        }
        $this->flush();
    }

    protected function flush() {
        $nbObjectUpdated = $this->import->getRowsInserted() + $this->import->getRowsUpdated();
        if (!$this->getOption('dry-run')) {
            if ($nbObjectUpdated % self::BATCH_SIZE == 0) {
                $this->em->flush();
            }
        }
        if ($nbObjectUpdated % self::BATCH_CLEAR_SIZE == 0) {
            $oldMemoryUsage = memory_get_usage(true);
            $this->em->clear();
            $this->registry->clear();
            $newMemoryUsage = memory_get_usage(true);
            $this->log("Old memory usage [$oldMemoryUsage] New [$newMemoryUsage]", true, self::LEVEL_DEBUG);
        }
    }

    protected function clearAll() {
        $this->em->clear();
        $this->registry->clear();
    }

    /**
     * Check if item already exists
     * @param array $item
     * @return bool
     */
    protected function alreadyExist($item) {
        $key = $this->getItemKey($item);
        if ($key) {
            if ($this->registry->has($key)) {
                return $this->registry->get($key);
            }
        }

        $repo = $this->getRepository($item);
        $criteria = array();
        foreach ($this->refFields as $fieldName) {
            $fieldName = $this->getFieldName($fieldName);
            $value  = isset($item[$fieldName]) ? $item[$fieldName] : null;
            if (is_object($value)) {
                if (!$this->em->getUnitOfWork()->isInIdentityMap($value)) {
                    return null;
                }
            }
            $criteria[$fieldName] = $value;
        }
        $obj = $repo->findOneBy($criteria);
        if ($obj) {
            return $obj;
        }
        return null;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function getOption($key) {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return false;
    }

    /**
     * @param      $value
     * @param bool $breakLine
     * @param int  $level
     */
    protected function log($value, $breakLine = true, $level = self::LEVEL_INFO) {
        if (is_array($this->currentItem)) {
            $ref = $this->getItemReference($this->currentItem);
            if ($ref) {
                $value = "[ref=$ref] : $value";
            }
        }
        if ($this->logger) {
            switch ($level) {
                case self::LEVEL_DEBUG:
                    if ($this->getOption('verbose')) {
                        $this->logger->debug("[Import - ".$this->name."] ".strip_tags($value));
                    }
                    break;
                case self::LEVEL_INFO:
                    $this->logger->info("[Import - ".$this->name."] ".strip_tags($value));
                    break;
                case self::LEVEL_ERROR:
                    $this->logger->err("[Import - ".$this->name."] ".strip_tags($value));
                    break;
            }
        }
        if ($this->output && ($level >= self::LEVEL_INFO || $this->getOption('verbose'))) {
            if ($breakLine) {
                $this->output->writeLn($value);
            } else {
                $this->output->write($value);
            }
        }
    }

    /**
     * @param $refFields
     */
    public function setRefFields($refFields)
    {
        if (!is_array($refFields)) {
            $this->refFields = array($refFields);
        }
        $this->refFields = $refFields;
    }

    /**
     * @param $key
     * @return null|string
     */
    protected function getFieldScope($key) {
        $pos = strpos($key, '.');
        if ($pos) {
            return substr($key, 0, $pos);
        }
        return null;
    }

    /**
     * @param $key
     * @return string
     */
    protected function getFieldName($key) {
        $pos = strpos($key, '.');
        if ($pos) {
            return substr($key, $pos + 1);
        }
        return $key;
    }

    /**
     * Renvoi la référence du traitement en cour
     * @param $item
     * @return mixed
     */
    protected function getItemReference($item) {
        $ref = array();
        foreach ($this->refFields as $fieldName) {
            $input = isset($item[$fieldName]) ? $item[$fieldName] : '';
            if (is_object($input)) {
                $uow = $this->em->getUnitOfWork();
                if ($uow->isInIdentityMap($input)) {
                    $ids = $uow->getEntityIdentifier($input);
                    $input = implode('|', $ids);
                } else {
                    $input = (string)$input;
                }
            }
            $ref[] = $input;
        }
        return implode('|', $ref);
    }

    /**
     * @param array $item
     *
     * @return null|string
     */
    protected function getItemKey(array &$item) {
        $field = $this->getItemReference($item);
        if (!empty($field)) {
            return sha1(strtolower($this->name.'||'.$field));
        }
        return null;
    }

    /**
     * @param array $item
     *
     * @return bool
     */
    public function getClass(array &$item) {
        return $this->getOption('class');
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Appelé lorsque tout les imports sont terminés
     * @param \DateTime $begin
     */
    public function finishAll($begin)
    {

    }
}