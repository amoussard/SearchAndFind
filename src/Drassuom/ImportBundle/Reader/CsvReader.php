<?php

namespace Drassuom\ImportBundle\Reader;

use Symfony\Component\HttpFoundation\File\File;
use JMS\DiExtraBundle\Annotation as DI;


/**
 * Csv reader
 *
 * @DI\Service("drassuom_import.reader.csv")
 */
class CsvReader implements \SeekableIterator
{
    /**
     * The field delimiter (one character only)
     *
     * @var string
     */
    private $delimiter = ';';

    /**
     * The field enclosure character (one character only)
     *
     * @var string
     */
    private $enclosure = '"';

    /**
     * The field escape character (one character only)
     *
     * @var string
     */
    private $escape    = '\\';

    /**
     * Number of the row that contains the column names
     *
     * @var int
     */
    protected $headerRowNumber;

    /**
     * CSV file
     *
     * @var \SplFileObject
     */
    protected $file;

    /**
     * Column headers as read from the CSV file
     *
     * @var array
     */
    protected $columnHeaders;

    /**
     * @var int
     */
    protected $startAt = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $pointer = 0;

    /**
     * @param array $options
     */
    public function setOptions(array &$options) {
        if (!empty($options['delimiter'])) {
            $this->delimiter = $options['delimiter'];
        }
        if (!empty($options['enclosure'])) {
            $this->delimiter = $options['enclosure'];
        }
        if (!empty($options['escape'])) {
            $this->delimiter = $options['escape'];
        }
        if (!empty($options['start'])) {
            $this->startAt = $options['start'];
        } else {
            $this->startAt = 1;
        }
        if (!empty($options['use_column_header'])) {
            $this->headerRowNumber = !empty($options['column_header_row']) ? intval($options['column_header_row']) : 1;
            $this->startAt += $this->headerRowNumber;
        }
        if (!empty($options['limit'])) {
            $this->limit = $options['limit'];
        }
    }


    /**
     * @param \Symfony\Component\HttpFoundation\File\File $file
     * @return CsvReader
     */
    public function setFile(File $file)
    {
        $this->file = $file->openFile();
        $this->file->setFlags(\SplFileObject::READ_CSV |  \SplFileObject::SKIP_EMPTY |  \SplFileObject::READ_AHEAD);
        $this->file->setCsvControl(
            $this->delimiter,
            $this->enclosure,
            $this->escape
        );

        return $this;
    }

    /**
     * Return the current row as an array
     *
     * If a header row has been set, an associative array will be returned
     *
     * @return array
     */
    public function current()
    {
        $line = $this->file->current();

        // If the CSV has column headers, use them to construct an associative
        // array for the columns in this line
        if (!empty($this->columnHeaders)) {
            if (count($this->columnHeaders) == count($line)) {
                return array_combine(array_values($this->columnHeaders), $line);
            }

        } else {
            // Else just return the column values
            return $line;
        }
        return null;
    }

    /**
     * Get column headers
     *
     * @return array
     */
    public function getColumnHeaders()
    {
        return $this->columnHeaders;
    }

    /**
     * Set column headers
     *
     * @param array $columnHeaders
     * @return CsvReader
     */
    public function setColumnHeaders(array $columnHeaders)
    {
        $this->columnHeaders = $columnHeaders;
        return $this;
    }

    /**
     * Rewind the file pointer
     *
     * If a header row has been set, the pointer is set just below the header
     * row. That way, when you iterate over the rows, that header row is
     * skipped.
     *
     */
    public function rewind()
    {
        $this->file->rewind();
        $this->pointer = $this->startAt - 1;
        $this->file->seek($this->pointer);
    }

    /**
     * Set header row number
     *
     * @param int $rowNumber Number of the row that contains column header names
     * @return CsvReader
     */
    public function setHeaderRowNumber($rowNumber)
    {
        $this->headerRowNumber = $rowNumber;
        $this->columnHeaders = $this->readHeaderRow($rowNumber);
        return $this;
    }

    /**
     * Count number of rows in CSV
     *
     * @return int
     */
    public function count()
    {
        return $this->countRows();
    }

    /**
     * Count number of rows in CSV
     *
     * @return int
     */
    public function countRows()
    {
        $rows = 0;
        foreach ($this as $row) {
            $rows++;
        }
        return $rows;
    }

    public function next()
    {
        $this->pointer++;
        return $this->file->next();
    }

    public function valid()
    {
        if (!$this->file->valid()) {
            return false;
        }
        if ($this->limit) {
            return ($this->pointer - ($this->startAt - 1) < $this->limit);
        }
        return true;
    }

    public function key()
    {
        return $this->file->key();
    }

    public function seek($pointer)
    {
        $this->file->seek($pointer);
    }

    public function getFields()
    {
        return $this->columnHeaders;
    }

    /**
     * Get a row
     *
     * @param int $number   Row number
     * @return array
     */
    public function getRow($number)
    {
        $this->seek($number);
        return $this->current();
    }

    /**
     * Read header row from CSV file
     *
     * @param \SplFileObject
     * @return array        Column headers
     */
    protected function readHeaderRow($rowNumber)
    {
        $this->file->seek($rowNumber);
        $headers = $this->file->current();
        return $headers;
    }

    /**
     * @param string $delimiter
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @param string $enclosure
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
    }

    /**
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * @param string $escape
     */
    public function setEscape($escape)
    {
        $this->escape = $escape;
    }

    /**
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }
}