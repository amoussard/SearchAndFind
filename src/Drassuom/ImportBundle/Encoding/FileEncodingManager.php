<?php

namespace Drassuom\ImportBundle\Encoding;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Description of FileEncodingManager.php
 *
 * @author: m.monsang <m.monsang@novactive.com>
 */
class FileEncodingManager
{
    /**
     * @var \Symfony\Bridge\Monolog\Logger
     */
    protected $logger;

    /**
     * @var string $path
     */
    protected $path;

    /**
     * @param \Symfony\Bridge\Monolog\Logger $logger
     * @param $path
     */
    public function __construct(Logger $logger, $path) {
        $this->logger = $logger;
        $this->path = $path;
    }

    /**
     * Convert file if it's encoding don't match self::DEFAULT_ENCODING
     *
     * @param \Symfony\Component\HttpFoundation\File\File $oFile
     * @param string                                      $sFilename
     * @param string                                      $sToEncoding
     *
     * @throws \RuntimeException
     * @return bool
     */
    public function convertFileEncoding(File $oFile, $sFilename, $sToEncoding) {
        if ($oFile->isReadable()) {
            $sContent = file_get_contents($oFile->getRealPath());
            $sFromEncoding = mb_detect_encoding($sContent, "ISO-8859-1, UTF-8");
            if ($sFromEncoding != $sToEncoding) {
                $sReEncodedTargetDir = $this->path;

                $oFilesystem = new Filesystem();
                if (!is_dir($sReEncodedTargetDir)) {
                    if (!$oFilesystem->mkdir($sReEncodedTargetDir, 0777)) {
                        return false;
                    }
                } elseif (!is_writable($sReEncodedTargetDir)) {
                    throw new \RuntimeException("Can not convert file into [$sToEncoding]. Please check [$sReEncodedTargetDir] rights");
                }

                $sReEncodedTargetPath = $sReEncodedTargetDir.uniqid().'_'.$sFilename;
                file_put_contents($sReEncodedTargetPath, mb_convert_encoding($sContent, $sToEncoding, $sFromEncoding));
                $this->logger->info("File [$oFile] renencoded into [$sReEncodedTargetPath], from [$sFromEncoding] to [$sToEncoding]");
                return new \Symfony\Component\HttpFoundation\File\File($sReEncodedTargetPath);
            }
        } else {
            throw new \RuntimeException("can not read file [$oFile]");
        }
        return null;
    }

    /**
     * @param \Symfony\Bridge\Monolog\Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
}