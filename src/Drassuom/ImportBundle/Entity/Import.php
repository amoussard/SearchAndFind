<?php

namespace Drassuom\ImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

use Drassuom\ImportBundle\Exception\ImportException;

/**
 * Nova\ImportBundle\Entity\Import : store data relative to the import
 * @ORM\Table(name="import")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 */
class Import
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \Datetime $date
     *
     * @ORM\Column(name="date", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $date;

    /**
     * @var boolean $success
     *
     * @ORM\Column(name="success", type="boolean")
     */
    private $success = false;

    /**
     * @var integer $nbErrors
     *
     * @ORM\Column(name="nb_errors", type="integer")
     */
    private $nbErrors = 0;

    /**
     * Error List
     * @var \Doctrine\Common\Collections\ArrayCollection $errorList
     *
     * @ORM\Column(name="error_list", type="array", nullable=true)
     */
    private $errorList;

    /**
     * Warning List
     * @var \Doctrine\Common\Collections\ArrayCollection $warningList
     *
     * @ORM\Column(name="warning_list", type="array", nullable=true)
     */
    private $warningList;

    /**
     * @var integer $rowsInserted
     *
     * @ORM\Column(name="rows_inserted", type="integer")
     */
    private $rowsInserted = 0;

    /**
     * @var integer $rowsSkipped
     *
     * @ORM\Column(name="rows_skipped", type="integer")
     */
    private $rowsSkipped = 0;

    /**
     * @var integer $rowsUpdated
     *
     * @ORM\Column(name="rows_updated", type="integer")
     */
    private $rowsUpdated = 0;

    /**
     * @var \Symfony\Component\HttpFoundation\File\File $filename
     */
    private $file = null;

    /**
     * @var \Symfony\Component\HttpFoundation\File\File $filename
     */
    private $reEncodedFile = null;

    /**
     * @var string $originalFileName
     *
     * @ORM\Column(name="original_filename", type="string", nullable=false)
     */
    private $originalFileName = null;

    /*
     * @var string $filePath = null;
     *
     * @ORM\Column(name="file_path", type="string", nullable=false)
     */
    private $filePath = null;

    /**
     * @var int $beginAt
     *
     * @ORM\Column(name="begin_time", type="float", nullable=false)
     */
    private $beginTime = null;

    /**
     * @var int $beginAt
     *
     * @ORM\Column(name="end_time", type="float", nullable=false)
     */
    private $endTime = 0;

    /**
     * @var \Symfony\Component\HttpFoundation\File\File $archive
     */
    private $archive = null;

    /**
     * @var string $archiveFilePath
     *
     * @ORM\Column(name="archive_path", type="string", nullable=true)
     */
    private $archiveFilePath = null;

    /**
     * @var string
     */
    private $extractedDirPath = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->errorList = new \Doctrine\Common\Collections\ArrayCollection();
        $this->warningList = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set date
     *
     * @param \datetime $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * Get date
     *
     * @return \datetime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set success
     *
     * @param boolean $success
     */
    public function setSuccess($success)
    {
        $this->success = $success;
    }

    /**
     * Get success
     *
     * @return boolean
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Set nbErrors
     *
     * @param integer $nbErrors
     */
    public function setNbErrors($nbErrors)
    {
        $this->nbErrors = $nbErrors;
    }

    /**
     * Get nbErrors
     *
     * @return integer
     */
    public function getNbErrors() {
        return $this->nbErrors;
    }

    /**
     * @param int $rowsInserted
     */
    public function setRowsInserted($rowsInserted)
    {
        $this->rowsInserted = $rowsInserted;
    }

    /**
     * @return int
     */
    public function getRowsInserted()
    {
        return $this->rowsInserted;
    }

    /**
     * @param int $rowsSkipped
     */
    public function setRowsSkipped($rowsSkipped)
    {
        $this->rowsSkipped = $rowsSkipped;
    }

    /**
     * @return int
     */
    public function getRowsSkipped()
    {
        return $this->rowsSkipped;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\File $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function getFile()
    {
        return $this->file;
    }


    /**
     * @param \Symfony\Component\HttpFoundation\File\File $archive
     */
    public function setArchive($archive)
    {
        $this->archive = $archive;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function getArchive()
    {
        return $this->archive;
    }

    public function getClientFileName() {
        $file = $this->getFile();
        if ($file === null) {
            return '';
        }
        if ($this->originalFileName) {
            return $this->originalFileName;
        }
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            return $file->getClientOriginalName();
        }
        return $file->getFilename();
    }

    public function getArchiveFileName() {
        $archive = $this->getArchive();
        if ($archive === null) {
            return '';
        }
        if ($archive instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $archive */
            return $archive->getClientOriginalName();
        }
        return $archive->getFilename();
    }

    /**
     * @param string $extractedDirPath
     */
    public function setExtractedDirPath($extractedDirPath)
    {
        $this->extractedDirPath = $extractedDirPath;
    }

    /**
     * @return string
     */
    public function getExtractedDirPath()
    {
        return $this->extractedDirPath;
    }

    /**
     * @param int $beginTime
     */
    public function setBeginTime($beginTime)
    {
        $this->beginTime = $beginTime;
    }

    /**
     * @return int
     */
    public function getBeginTime()
    {
        return $this->beginTime;
    }

    /**
     * @param int $endTime
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * @return int
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    public function getExecutionTime() {
        if (!$this->endTime) {
            return 0;
        }
        return round(($this->endTime - $this->beginTime), 2)."s";
    }

    /**
     * @param string $originalFileName
     */
    public function setOriginalFileName($originalFileName)
    {
        $this->originalFileName = $originalFileName;
    }

    /**
     * @return string
     */
    public function getOriginalFileName()
    {
        return $this->originalFileName;
    }

    /**
     *
     */
    public function __clone() {
        $this->originalFileName = null;
        $this->reEncodedFile = null;
        $this->errorList = new \Doctrine\Common\Collections\ArrayCollection();
        $this->nbErrors = 0;
    }

    /**
     * @param \Doctrine\Common\Collections\ArrayCollection $errorList
     */
    public function setErrorList($errorList)
    {
        $this->errorList = $errorList;
    }

    /**
     * @param $error
     */
    public function addError($error)
    {
        if (!$this->errorList->contains($error)) {
            $this->errorList[] = $error;
        }
    }

    /**
     * @param $warning
     */
    public function addWarning($warning)
    {
        if (!$this->warningList->contains($warning)) {
            $this->warningList[] = $warning;
        }
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getErrorList()
    {
        $aRet = array();

        foreach ($this->errorList as $oError) {
            if (!$oError instanceof ImportException || $oError->isFatal()) {
                $aRet[] = $oError;
            }
        }
        return $aRet;
    }

    /**
     * Renvoi la liste de tout les warning
     * @return array
     */
    public function getWarningList()
    {
        $aRet = array();

        foreach ($this->errorList as $oError) {
            if ($oError instanceof ImportException && !$oError->isFatal()) {
                $aRet[] = $oError;
            }
        }
        foreach ($this->warningList as $oWarning) {
            $aRet[] = $oWarning;
        }
        return $aRet;
    }

    /**
     * @param int $rowsUpdated
     */
    public function setRowsUpdated($rowsUpdated)
    {
        $this->rowsUpdated = $rowsUpdated;
    }

    /**
     * @return int
     */
    public function getRowsUpdated()
    {
        return $this->rowsUpdated;
    }

    /**
     * @ORM\PrePersist
     */
    public function prePersist() {
        if ($this->file && !$this->filePath) {
            $this->filePath = $this->file->getPath();
        }
        if ($this->archive && !$this->archiveFilePath) {
            $this->archiveFilePath = $this->archive->getPath();
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\File $reEncodedFile
     */
    public function setReEncodedFile($reEncodedFile)
    {
        $this->reEncodedFile = $reEncodedFile;
    }

    /**
     * @param bool $returnOriginal
     *
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function getReEncodedFile($returnOriginal = true)
    {
        if ($this->reEncodedFile) {
            return $this->reEncodedFile;
        } elseif ($returnOriginal) {
            return $this->file;
        }
        return null;
    }

    /**
     * @param $filePath
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * @return null
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param string $archiveFilePath
     */
    public function setArchiveFilePath($archiveFilePath)
    {
        $this->archiveFilePath = $archiveFilePath;
    }

    /**
     * @return string
     */
    public function getArchiveFilePath()
    {
        return $this->archiveFilePath;
    }
}