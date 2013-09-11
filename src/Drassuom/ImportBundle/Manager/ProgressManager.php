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
     * @param array $config
     *
     * @DI\InjectParams({
     *     "config"         = @DI\Inject("%drassuom_import.config%"),
     * })
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Create an lock file
     */
    public function lock($options) {
        $options = array_merge($this->config, $options);

        if (!empty($options['no-lock'])) {
            return true;
        }
        if (isset($options['pid'])) {
            $this->currentPid = $options['pid'];
        }
        $lockFile = $this->config['lock_file'];
        if (file_exists($lockFile)) {
            if (!is_readable($lockFile)) {
                return false;
            }
            $pid = trim(basename(file_get_contents($lockFile)));
            if ($pid && is_dir("/proc/$pid")) {
                $i = 0;
                // don't wait until the end of the process at this time
                while (file_exists($lockFile)) {
                    sleep(++$i);
                    if ($i > self::LOCK_EXCEEDED) {
                        return false;
                    }
                }
            }
        }
        $pid = $this->getPid();
        @file_put_contents($lockFile, (string)$pid, FILE_APPEND | LOCK_EX);
        return true;
    }

    public function unlock() {
        $this->currentPid = null;
        $lockFile = $this->config['lock_file'];
        @unlink($lockFile);
        $progressFile = $this->config['progress_file'];
        @unlink($progressFile);
    }

    /**
     *
     */
    protected function setupSignals() {
        declare(ticks = 1);
        if (function_exists("pcntl_signal")) {
            pcntl_signal(SIGINT, array( &$this, "onStop" ));
            pcntl_signal(SIGUSR1, array( &$this, "onProgress" ));
            pcntl_signal(SIGUSR2, array( &$this, "onProgress" ));
        }
    }

    /**
     * @param $signal
     */
    public  function onStop($signal) {
        if ($this->currentWorkflow) {
            $this->currentWorkflow->setStop(true);
        }
        $this->stop = true;
    }

    /**
     * @param $signal
     */
    public  function onProgress($signal) {
        if ($this->currentWorkflow) {
            $progress = $this->getProgressInfos($this->currentWorkflow->getCurrentRow());
            $this->writeProgress($progress);
        }
    }

    /**
     * @param array $progressInfos
     */
    protected function writeProgress(array &$progressInfos) {
        $progressFile = $this->config['progress_file'];
        $pid = $this->getPid();
        @file_put_contents($progressFile, json_encode(array($pid => $progressInfos)));
    }

    /**
     * @return bool|mixed
     */
    public function getCurrentImportProgression() {
        $progressFile = $this->config['progress_file'];
        return json_decode(@file_get_contents($progressFile));

    }

    /**
     * @param Workflow  $worflow
     * @param           $countRow
     */
    public function setupProgress(Workflow $worflow, $countRow) {
        $this->beginAt = microtime(true);
        $this->countRow = $countRow;
        if ($this->output) {
            $this->output->write(sprintf("\r%80s\r", ''));
        }
        $this->currentWorkflow = $worflow;
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
     * @param $currentRow
     */
    public function showProgress($currentRow) {
        if ($this->countRow > 1000) {
            $step = intval($this->countRow / 100);
        } else {
            $step = intval($this->countRow / 10);
        }
        if ($step && (($currentRow - 1) % $step == 0)) {
            $progresssInfos = $this->getProgressInfos($currentRow);
            $this->writeProgress($progresssInfos);
            $progress = $progresssInfos['progress'];
            if ($this->logger) {
                $progress = ($progress) ? $progress : 'INF';
                $this->logger->debug(var_export($progresssInfos, 1));
            }
            if ($this->output && $progress) {
                $nbStepPassed = floor(($progress * 2) / 10);
                $nbStepMiss = 20 - $nbStepPassed;
                $infos = sprintf("<info>%02d %% </info><comment>%s</comment>",
                    $progress,
                    ($progresssInfos['timeLeft'] !== null) ? $progresssInfos['timeLeft'] : ''
                );
                $str = sprintf(
                    "\r[%'#".$nbStepPassed."s%".$nbStepMiss.'s] %s', '', '',
                    $infos
                );
                $this->output->write(sprintf("\r%80s", ''));
                $this->output->write($str);
            }
        }
    }

    /**
     * @param $currentRow
     * @return array
     */
    public function getProgressInfos($currentRow) {
        $progress = null;
        $expectedTime = null;
        $timeLeft = null;
        $currentTimestamp = microtime(true);
        $elapsed = intval($currentTimestamp - $this->beginAt);
        if ($this->countRow) {
            $progress = round($currentRow / floatval($this->countRow) * 100, 2);
            if ($elapsed > 60 && $progress) {
                // calcul de l'estimation de la fin de l'import
                $expectedTime = ceil($elapsed * 100 / $progress);
                $timeLeft = $expectedTime - $elapsed;
            }
        }

        return array(
            'currentRow'    => $currentRow,
            'beginAt'       => $this->beginAt,
            'countRow'      => $this->countRow,
            'expectedTime'  => ($expectedTime) ? null : "$expectedTime s",
            'elapsed'       => "$elapsed s",
            'timeLeft'      => ($timeLeft) ? "$timeLeft s" : null,
            'progress'      => round($progress),
        );
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * @param OutputInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

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