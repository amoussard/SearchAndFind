<?php

namespace Drassuom\ImportBundle\Manager;

use Drassuom\ImportBundle\Workflow;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bridge\Monolog\Logger;
use JMS\DiExtraBundle\Annotation as DI;


/**
 * Description of ImportManager.php
 *
 * @DI\Service("drassuom_import.progress.manager")
 */
class ProgressManager
{
    const LOCK_EXCEEDED = 5;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var  \Symfony\Component\Console\Output\OutputInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $currentRow = 0;

    /**
     * @var int
     */
    protected $countRow = 0;

    /**
     * @var \DateTime
     */
    protected $beginAt;

    /**
     * @var Workflow
     */
    protected $currentWorkflow;

    protected $currentPid = null;

    /**
     * @var int
     */
    protected $memoryUsage = 0;

    /**
     * @param array $aConfig
     *
     * @DI\InjectParams({
     *     "aConfig"         = @DI\Inject("%drassuom_import.config%"),
     * })
     */
    public function __construct(array $aConfig) {
        $this->config = $aConfig;
    }

    /**
     * Create an lock file
     *
     * @param array $aOptions
     *
     * @return bool
     */
    public function lock(array $aOptions) {
        $aOptions = array_merge($this->config, $aOptions);

        if (!empty($aOptions['no-lock'])) {
            return true;
        }
        if (isset($aOptions['pid'])) {
            $this->currentPid = $aOptions['pid'];
        }
        $sLockFile = $this->config['lock_file'];
        if (file_exists($sLockFile)) {
            if (!is_readable($sLockFile)) {
                return false;
            }
            $iPid = trim(basename(file_get_contents($sLockFile)));
            if ($iPid && is_dir("/proc/$iPid")) {
                $i = 0;
                // don't wait until the end of the process at this time
                while (file_exists($sLockFile)) {
                    sleep(++$i);
                    if ($i > self::LOCK_EXCEEDED) {
                        return false;
                    }
                }
            }
        }
        $iPid = $this->getPid();
        @file_put_contents($sLockFile, (string)$iPid, FILE_APPEND | LOCK_EX);
        return true;
    }

    public function unlock() {
        $this->currentPid = null;
        $sLockFile = $this->config['lock_file'];
        @unlink($sLockFile);
        $sProgressFile = $this->config['progress_file'];
        @unlink($sProgressFile);
    }

    protected function setupSignals() {
        declare(ticks = 1);
        if (function_exists("pcntl_signal")) {
            pcntl_signal(SIGINT, array(&$this, "onStop"));
            pcntl_signal(SIGUSR1, array(&$this, "onProgress"));
            pcntl_signal(SIGUSR2, array(&$this, "onProgress"));
        }
    }

    /**
     * @param integer $iSignal
     */
    public  function onStop($iSignal) {
        if ($this->currentWorkflow) {
            $this->currentWorkflow->setStop(true);
        }
        $this->stop = true;
    }

    /**
     * @param integer $iSignal
     */
    public  function onProgress($iSignal) {
        if ($this->currentWorkflow) {
            $progress = $this->getProgressInfos($this->currentWorkflow->getCurrentRow());
            $this->writeProgress($progress);
        }
    }

    /**
     * @param array $aProgressInfos
     */
    protected function writeProgress(array &$aProgressInfos) {
        $sProgressFile = $this->config['progress_file'];
        $iPid = $this->getPid();
        @file_put_contents($sProgressFile, json_encode(array($iPid => $aProgressInfos)));
    }

    /**
     * @return mixed
     */
    public function getCurrentImportProgression() {
        $progressFile = $this->config['progress_file'];
        return json_decode(@file_get_contents($progressFile));
    }

    /**
     * @param Workflow $oWorkflow
     * @param integer  $iCountRow
     */
    public function setupProgress(Workflow $oWorkflow, $iCountRow) {
        $this->beginAt = microtime(true);
        $this->memoryUsage = memory_get_usage(true);
        $this->countRow = $iCountRow;
        if ($this->output) {
            $this->output->write(sprintf("\r%80s\r", ''));
        }
        $this->currentWorkflow = $oWorkflow;
        $this->setupSignals();
    }

    /**
     * Clear current progression
     */
    public function clearProgress() {
        if ($this->output) {
            $this->output->write(sprintf("\r%80s\r", ''));
        }
    }

    /**
     * @param integer   $iCurrentRow
     */
    public function showProgress($iCurrentRow) {
        if ($this->countRow > 1000) {
            $iStep = intval($this->countRow / 100);
        } else {
            $iStep = intval($this->countRow / 10);
        }
        if ($iStep && (($iCurrentRow - 1) % $iStep == 0)) {
            $aProgressInfos = $this->getProgressInfos($iCurrentRow);
            $this->writeProgress($aProgressInfos);
            $iProgress = $aProgressInfos['progress'];
            if ($this->logger) {
                $iProgress = ($iProgress) ? $iProgress : 'INF';
                $this->logger->debug(var_export($aProgressInfos, 1));
            }
            if ($this->output && $iProgress) {
                $iNbStepPassed = floor(($iProgress * 2) / 10);
                $iNbStepMiss = 20 - $iNbStepPassed;
                $sInfos = sprintf("<info>%02d %% </info> Temps passé : <comment>%s</comment> Temps restant : <comment>%s</comment> Utilisation mémoire : <comment>%s</comment>",
                    $iProgress,
                    ($aProgressInfos['elapsed'] !== null) ? $aProgressInfos['elapsed'] : '',
                    ($aProgressInfos['timeLeft'] !== null) ? $aProgressInfos['timeLeft'] : '',
                    ($aProgressInfos['memoryUsage'] !== null) ? $aProgressInfos['memoryUsage'] : ''
                );
                $sStr = sprintf(
                    "\r[%'#".$iNbStepPassed."s%".$iNbStepMiss.'s] %s', '', '',
                    $sInfos
                );
                $this->output->write(sprintf("\r%80s", ''));
                $this->output->write($sStr);
            }
        }
    }

    /**
     * @param integer $iCurrentRow
     *
     * @return array
     */
    public function getProgressInfos($iCurrentRow) {
        $fProgress = null;
        $iExpectedTime = null;
        $iTimeLeft = null;
        $iCurrentTimestamp = microtime(true);
        $iElapsed = intval($iCurrentTimestamp - $this->beginAt);
        if ($this->countRow) {
            $fProgress = round($iCurrentRow / floatval($this->countRow) * 100, 2);
            if ($iElapsed > 60 && $fProgress) {
                // calcul de l'estimation de la fin de l'import
                $iExpectedTime = ceil($iElapsed * 100 / $fProgress);
                $iTimeLeft = $iExpectedTime - $iElapsed;
            }
        }
        $this->memoryUsage = memory_get_usage(true);

        return array(
            'currentRow'    => $iCurrentRow,
            'beginAt'       => $this->beginAt,
            'countRow'      => $this->countRow,
            'expectedTime'  => ($iExpectedTime) ? null : "$iExpectedTime s",
            'elapsed'       => "$iElapsed s",
            'timeLeft'      => ($iTimeLeft) ? "$iTimeLeft s" : null,
            'progress'      => round($fProgress),
            'memoryUsage'   => round($this->memoryUsage / 1000000, 2),
        );
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * @return int|string
     */
    protected function getPid() {
        $pid = '';
        if (isset($this->currentPid)) {
            return $this->currentPid;
        }
        if (function_exists('posix_getpid')) {
            $pid = posix_getpid();
        }
        return $pid;
    }
}