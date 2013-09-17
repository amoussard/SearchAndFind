<?php

namespace Drassuom\LocationBundle\Entity;

use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations
use Doctrine\ORM\Mapping as ORM;

/**
 * Country
 *
 * @ORM\Entity
 */
class Country extends Location
{
    /**
     * @var string
     *
     * @ORM\Column(name="iso", type="string", length=2)
     */
    protected $iso;

    /**
     * @var string
     *
     * @ORM\Column(name="iso3", type="string", length=3)
     */
    protected $iso3;

    /**
     * @var string
     *
     * @ORM\Column(name="iso_numeric", type="string", length=3)
     */
    protected $isoNumeric;

    /**
     * @var string
     *
     * @ORM\Column(name="fips", type="string", length=2)
     */
    protected $fips;

    /**
     * @var integer
     *
     * @ORM\Column(name="area", type="integer")
     */
    protected $area;

    /**
     * @var integer
     *
     * @ORM\Column(name="population", type="integer")
     */
    protected $population;

    /**
     * @var string
     *
     * @ORM\Column(name="tld", type="string", length=5)
     */
    protected $tld;

    /**
     * @var string
     *
     * @ORM\Column(name="currency_code", type="string", length=3)
     */
    protected $currencyCode;

    /**
     * @var string
     *
     * @ORM\Column(name="currency_name", type="string")
     */
    protected $currencyName;

    /**
     * @var string
     *
     * @ORM\Column(name="ind_phone", type="string")
     */
    protected $indPhone;

    /**
     * @var string
     *
     * @ORM\Column(name="postal_code_format", type="string")
     */
    protected $postalCodeFormat;

    /**
     * @var string
     *
     * @ORM\Column(name="postal_code_regex", type="string")
     */
    protected $postalCodeRegex;

    /**
     * @var string
     *
     * @ORM\Column(name="langagues", type="string")
     */
    protected $languages;

    /**
     * @param int $area
     */
    public function setArea($area) {
        $this->area = $area;
    }

    /**
     * @return int
     */
    public function getArea() {
        return $this->area;
    }

    /**
     * @param string $currencyCode
     */
    public function setCurrencyCode($currencyCode) {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return string
     */
    public function getCurrencyCode() {
        return $this->currencyCode;
    }

    /**
     * @param string $currencyName
     */
    public function setCurrencyName($currencyName) {
        $this->currencyName = $currencyName;
    }

    /**
     * @return string
     */
    public function getCurrencyName() {
        return $this->currencyName;
    }

    /**
     * @param string $fips
     */
    public function setFips($fips) {
        $this->fips = $fips;
    }

    /**
     * @return string
     */
    public function getFips() {
        return $this->fips;
    }

    /**
     * @param string $indPhone
     */
    public function setIndPhone($indPhone) {
        $this->indPhone = $indPhone;
    }

    /**
     * @return string
     */
    public function getIndPhone() {
        return $this->indPhone;
    }

    /**
     * @param string $iso
     */
    public function setIso($iso) {
        $this->iso = $iso;
    }

    /**
     * @return string
     */
    public function getIso() {
        return $this->iso;
    }

    /**
     * @param string $iso3
     */
    public function setIso3($iso3) {
        $this->iso3 = $iso3;
    }

    /**
     * @return string
     */
    public function getIso3() {
        return $this->iso3;
    }

    /**
     * @param string $isoNumeric
     */
    public function setIsoNumeric($isoNumeric) {
        $this->isoNumeric = $isoNumeric;
    }

    /**
     * @return string
     */
    public function getIsoNumeric() {
        return $this->isoNumeric;
    }

    /**
     * @param string $languages
     */
    public function setLanguages($languages) {
        $this->languages = $languages;
    }

    /**
     * @return string
     */
    public function getLanguages() {
        return $this->languages;
    }

    /**
     * @param int $population
     */
    public function setPopulation($population) {
        $this->population = $population;
    }

    /**
     * @return int
     */
    public function getPopulation() {
        return $this->population;
    }

    /**
     * @param string $postalCodeFormat
     */
    public function setPostalCodeFormat($postalCodeFormat) {
        $this->postalCodeFormat = $postalCodeFormat;
    }

    /**
     * @return string
     */
    public function getPostalCodeFormat() {
        return $this->postalCodeFormat;
    }

    /**
     * @param string $postalCodeRegex
     */
    public function setPostalCodeRegex($postalCodeRegex) {
        $this->postalCodeRegex = $postalCodeRegex;
    }

    /**
     * @return string
     */
    public function getPostalCodeRegex() {
        return $this->postalCodeRegex;
    }

    /**
     * @param string $tld
     */
    public function setTld($tld) {
        $this->tld = $tld;
    }

    /**
     * @return string
     */
    public function getTld() {
        return $this->tld;
    }


}
