<?php

namespace Drassuom\ImportBundle\Manager;

use Symfony\Component\DependencyInjection\Container;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Translation\IdentityTranslator;
use Doctrine\ORM\EntityManager;

use Drassuom\ImportBundle\Workflow;
use Drassuom\ImportBundle\Entity\Import;
use Drassuom\ImportBundle\Encoding\FileEncodingManager;
use Drassuom\ImportBundle\Writer\ORM\BaseWriter;

use Drassuom\ImportBundle\Exception\ImportException;
use Drassuom\ImportBundle\Exception\ConfigException;

/**
 * Description of ImportManager.php
 */
class ImportManager
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var \Symfony\Bridge\Monolog\Logger
     */
    protected $logger;

    /**
     * @var \Symfony\Bridge\Monolog\Logger
     */
    protected $translator;

    /**
     * @var array
     */
    protected $writerList;

    /**
     * @var FileEncodingManager
     */
    protected $encodingManager;

    /**
     * @var bool $stop
     */
    protected $stop = false;

    /**
     * @var Workflow
     */
    protected $currentWorkflow;

    /**
     * @var ProgressManager
     */
    protected $progressManager;

    /**
     * @var array
     */
    protected $options = array(
        'throwException'        => false,
        'stopOnError'           => true,
        'rollback'              => true,
        'do-archive'            => true,
    );

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container
     * @param \Doctrine\ORM\EntityManager                      $em
     * @param FileEncodingManager                              $encodingManager
     * @param \Symfony\Bridge\Monolog\Logger                   $logger
     * @param IdentityTranslator                               $translator
     * @param array                                            $config
     * @param ProgressManager                                  $progressManager
     */
    public function __construct(Container $container, EntityManager $em, FileEncodingManager $encodingManager, Logger $logger, IdentityTranslator $translator, array $config, ProgressManager $progressManager) {
        $this->container = $container;
        $this->em = $em;
        $this->config = $config;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->encodingManager = $encodingManager;
        $this->encodingManager->setLogger($logger);
        $this->writerList = array();
        $this->progressManager = $progressManager;
    }

    /**
     * @param array $aImportList
     * @param array $aOptions
     *
     * @throws ImportException
     * @return Import
     */
    public function import($aImportList, $aOptions = array()) {
        $this->setOptions($aOptions);
        $writer = null;
        $begin = new \DateTime();

        if (!is_array($aImportList)) {
            $aImportList = array($aImportList);
        }

        if (!$this->progressManager->lock($aOptions)) {
            if (count($aImportList) > 0) {
                /** @var Import $oImport */
                $oImport = $aImportList[0];
                $oImport->setSuccess(false);
                $oImport->addError("Impossible d'effectuer cette tâche, car un autre import est déjà en cours d'exécution.");
            }
            return false;
        }

        // disable SQL logger (increase speed)
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        foreach ($aImportList as $sType => $oImport) {
            /** @var Import $oImport */
            try {
                if (!isset($this->config['types'][$sType])) {
                    throw new ImportException("Unknown type [$sType], please check configuration :\n\t".implode("\n\t", array_keys($this->config['types'])));
                }
                $this->prepareImport($oImport, $this->config['types'][$sType]);
                $this->currentWorkflow = $workflow = $this->prepareWorkflow($oImport, $sType, $aOptions);
                $oImport->setBeginTime(microtime(true));
                $workflow->process($oImport);
                $oImport->setEndTime(microtime(true));
                $this->log($oImport, $sType);
                $this->cleanUp($oImport);
            } catch (\Exception $e) {
                $this->logger->crit($e->getMessage());
                $this->logger->crit($e->getTraceAsString());
                if (!$e instanceof ImportException) {
                    $message = "Une erreur inconnue est survenue. Veuillez transmettre à l'administrateur le message suivant.<br /> [".get_class($e).":".$e->getMessage().']';
                    $this->logger->crit('Erreur inconnue. ['.$e->getMessage().']'."\n".$e->getTraceAsString());
                    $ex = new ImportException($message);
                    $ex->setLevel(ImportException::LEVEL_CRITICAL);
                }
                $oImport->addError($e);
                $this->log($oImport, $sType);
                $this->cleanUp($oImport);
                if ($this->getOption('throwException')) {
                    $this->progressManager->unlock();
                    throw $e;
                }
            }
            if ($this->stop) {
                break;
            }
        }

        /** @var BaseWriter $oWriter */
        foreach ($this->writerList as $oWriter) {
            $oWriter->finishAll($begin);
        }

        $this->progressManager->unlock();

        return $aImportList;
    }

    protected function setupSignals() {
        declare(ticks = 1);
        if (function_exists("pcntl_signal")) {
            pcntl_signal(SIGINT, array( &$this, "onStop" ));
            pcntl_signal(SIGUSR1, array( &$this, "onProgress" ));
        }
    }

    public  function onStop() {
        if ($this->currentWorkflow) {
            $this->currentWorkflow->setStop(true);
        }
        $this->stop = true;
    }

    public  function onProgress() {
        $progressFile = $this->config['progress_file'];
        if ($this->currentWorkflow) {
            $progress = $this->currentWorkflow->getProgressInfos();
            @file_put_contents($progressFile, json_encode($progress));
        }
    }

    /**
     * @param Import $oImport
     * @param array  $aTypeOptions
     */
    protected function prepareImport(Import $oImport, $aTypeOptions) {
        if (!empty($typeOptions['encoding'])) {
            $sReencodedFile = $this->encodingManager->convertFileEncoding($oImport->getFile(), $oImport->getClientFileName(), $aTypeOptions['encoding']);
            if ($sReencodedFile) {
                $oImport->setReEncodedFile($sReencodedFile);
            }
        }
    }

    /**
     * @param Import $oImport
     * @param string $sType
     * @param array  $aOptions
     *
     * @throws ConfigException
     * @return Workflow
     */
    public function prepareWorkflow(Import $oImport, $sType, array $aOptions) {
        if (empty($this->config['types'][$sType])) {
            throw new ConfigException("Unknown type [$sType]. Please check configuration.");
        }
        $aConfig = $this->config['types'][$sType];
        $oWorkflow = new Workflow($this->container, $this->translator);
        $oWriter = $this->prepareWriter($oImport, $sType, $aOptions);
        $this->writerList[get_class($oWriter)] = $oWriter;
        $oWorkflow->addWriter($oWriter);
        $aFormatOptions = $this->getFormatOptions($sType, $oImport->getFile());

        if (!empty($aFormatOptions['child'])) {
            $aChildConfig = $aFormatOptions['child'];
            $aChildOptions = $aOptions;
            $aChildOptions['is_child'] = true;
            $oChildWorkFlow = $this->prepareWorkflow($oImport, $aChildConfig['type'], $aChildOptions);
            $oWorkflow->setChildrenOptions(array(
                'workflow' => $oChildWorkFlow,
                'path'     => $aChildConfig['path'],
            ));
        }

        $oReader = $this->prepareReader($aFormatOptions, $oImport, $aOptions);
        $oWorkflow->setReader($oReader);

        $oMapper = $this->prepareMapper($aFormatOptions, $oImport, $aOptions);
        $oWorkflow->setMapper($oMapper);

        $oWorkflow->setValidator($this->prepareValidator($sType));
        $oWorkflow->setMappingOptions($aFormatOptions['mapping']);
        $oWorkflow->setConditionalMappingOptions($aFormatOptions['conditional_mapping']);
        $oWorkflow->setConditions($aConfig['conditions']);
        $oWorkflow->setConverters($aConfig['converters']);
        $oWorkflow->setPreValidators($aConfig['pre_validators']);
        $oWorkflow->setPostValidators($aConfig['post_validators']);
        $oWorkflow->setRefFields($this->getRefFields($sType));
        $oWorkflow->setProgressManager($this->progressManager);
        $oWorkflow->setLogger($this->logger);
        $this->progressManager->setLogger($this->logger);

        if (!empty($aOptions['output'])) {
            $oWorkflow->setOutput($aOptions['output']);
            $this->progressManager->setOutput($aOptions['output']);
        }

        if (!empty($aOptions['filters'])) {
            $aFilters = $aOptions['filters'];
            $oWorkflow->setFilters($aFilters);
        } elseif (!empty($aConfig['filters'])) {
            $aFilters = $aConfig['filters'];
            $oWorkflow->setFilters($aFilters);
        }

        return $oWorkflow;
    }


    /**
     * @param Import $oImport
     * @param string $sType
     * @param array  $aOptions
     *
     * @throws ConfigException
     * @return object
     */
    protected function prepareWriter(Import $oImport, $sType, $aOptions) {
        $sServiceId = $this->config['types'][$sType]['writer'];

        if (!$this->container->has($sServiceId)) {
            throw new ConfigException("Service [$sServiceId] does not exists. Please check configuration.");
        }

        $writer = $this->container->get($sServiceId);
        $writer->setCurrentImport($oImport);
        $writer->setOptions(array_merge($this->config['types'][$sType]['writer_options'], $aOptions));
        $writer->setLogger($this->logger);
        $writer->setRefFields($this->getRefFields($sType));

        return $writer;
    }

    /**
     * @param string    $sType
     *
     * @return array
     *
     * @throws ConfigException
     */
    protected function getRefFields($sType) {
        if (!empty($this->config['types'][$sType]['ref_fields'])) {
            return $this->config['types'][$sType]['ref_fields'];
        }

        if (!empty($this->config['types'][$sType]['ref_field_name'])) {
            return array($this->config['types'][$sType]['ref_field_name']);
        }

        throw new ConfigException('Please fill ref_fields or ref_field_name section');
    }


    /**
     * @param string    $sType
     *
     * @throws ConfigException
     *
     * @return object
     */
    protected function prepareValidator($sType) {
        $sServiceId = $this->config['types'][$sType]['validator'];
        if (!$this->container->has($sServiceId)) {
            throw new ConfigException("Validator [$sServiceId] does not exists. Please check configuration.");
        }

        $oValidator = $this->container->get($sServiceId);

        return $oValidator;
    }

    /**
     * @param string                                      $sType
     * @param \Symfony\Component\HttpFoundation\File\File $oFile
     *
     * @throws ConfigException
     *
     * @return array
     */
    protected function getFormatOptions($sType, \Symfony\Component\HttpFoundation\File\File $oFile) {
        $sExt = $oFile->getExtension();
        if (empty($sExt) && $oFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $sFilename = $oFile->getClientOriginalName();
            $sExt = pathinfo($sFilename, PATHINFO_EXTENSION);
        }
        if (empty($sExt)) {
            $sExt = $oFile->guessExtension();
        }
        $sExt = strtolower($sExt);
        if (!array_key_exists($sExt, $this->config['types'][$sType]['known_extensions'])) {
            throw new ConfigException("Do not handle this extension [$sExt] for this type [$sType]. Please check configuration.");
        }
        $sFormat = $this->config['types'][$sType]['known_extensions'][$sExt];
        if (!$sFormat) {
            $sFormat = $sExt;
        }
        return $this->config['types'][$sType][$sFormat];
    }


    /**
     * @param array     $aFormatOptions
     * @param Import    $oImport
     * @param array     $aOptions
     *
     * @throws ConfigException
     *
     * @return object
     */
    protected function prepareReader(array &$aFormatOptions, Import $oImport, array $aOptions) {
        $sServiceId = $aFormatOptions['reader'];
        if (!$this->container->has($sServiceId)) {
            throw new ConfigException("Reader [$sServiceId] does not exists. Please check configuration.");
        }
        $aReaderOptions = array_merge($aFormatOptions, $aOptions);
        $oReader = $this->container->get($sServiceId);
        $oReader->setOptions($aReaderOptions);
        $oReader->setFile($oImport->getReEncodedFile());
        return $oReader;
    }

    /**
     * @param array $aFormatOptions
     *
     * @throws ConfigException
     *
     * @return object
     */
    protected function prepareMapper(array &$aFormatOptions) {
        $sServiceId = $aFormatOptions['mapper'];
        if (!$this->container->has($sServiceId)) {
            throw new ConfigException("Mapper [$sServiceId] does not exists. Please check configuration.");
        }
        $oMapper = $this->container->get($sServiceId);

        return $oMapper;
    }

    /**
     * @param Import $import
     */
    protected function cleanUp(Import $import) {
        $reEncodedFile = $import->getReEncodedFile(false);
        if ($reEncodedFile) {
            $path = $reEncodedFile->getRealPath();
            $this->logger->debug("$reEncodedFile deleted");
            @unlink($path);
        }
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options) {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @param $key
     * @return bool
     */
    protected function getOption($key) {
        if (!isset($this->options[$key])) {
            return false;
        }
        return $this->options[$key];
    }

    /**
     * @param Import    $oImport
     * @param string    $sTtype
     */
    protected function log(Import $oImport, $sTtype) {
        if ($oImport->getSuccess()) {
            $this->logger->info('** Success**');
            $this->logger->info('Rows Inserted: '.$oImport->getRowsInserted());
            $this->logger->info('Rows Updated: '.$oImport->getRowsUpdated());
            $this->logger->info('Rows Skipped: '.$oImport->getRowsSkipped());
            $iNbErrors = $oImport->getNbErrors();
            if ($iNbErrors > 0) {
                $this->logger->info('Errors: '.$iNbErrors);
            }
        } else {
            $this->logger->err('!! Error !!');
        }
        $this->logger->info('File name: '.$oImport->getClientFileName());
        $this->logger->info('File path: '.$oImport->getFile());
        $this->logger->info('Type: '.$sTtype);
        $this->logger->info('Execution time: '.$oImport->getExecutionTime());
        $aErrorList = $oImport->getErrorList();
        foreach ($aErrorList as $oError) {
            $this->logger->err("Error: ". $oError);
        }
    }
}