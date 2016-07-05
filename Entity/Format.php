<?php

namespace HBM\MediaDeliveryBundle\Entity;

/**
 * Defines an image format.
 */
class Format {

  /** @var array */
  private $configSuffixes;

  /** @var string */
  private $format;

  /** @var boolean */
  private $retina = FALSE;

  /** @var boolean */
  private $blurred = FALSE;

  /** @var boolean */
  private $watermarked = FALSE;


  public function __construct($format, $configSuffixes) {
    $this->format = $format;
    $this->configSuffixes = $configSuffixes;
  }

  /****************************************************************************/

  /**
   * @return string
   */
  public function getFormat() {
    return $this->format;
  }

  /**
   * @param string $format
   */
  public function setFormat($format) {
    $this->format = $format;
  }

  /**
   * @return boolean
   */
  public function isRetina() {
    return $this->retina;
  }

  /**
   * @param boolean $retina
   */
  public function setRetina($retina) {
    $this->retina = $retina;
  }

  /**
   * @return boolean
   */
  public function isBlurred() {
    return $this->blurred;
  }

  /**
   * @param boolean $blurred
   */
  public function setBlurred($blurred) {
    $this->blurred = $blurred;
  }

  /**
   * @return boolean
   */
  public function isWatermarked() {
    return $this->watermarked;
  }

  /**
   * @param boolean $watermarked
   */
  public function setWatermarked($watermarked) {
    $this->watermarked = $watermarked;
  }

  /****************************************************************************/

  public function getFormatAdjusted() {
    $formatString = $this->getFormat();

    if ($this->isBlurred()) {
      $formatString .= $this->configSuffixes['blurred']['format'];
    } elseif ($this->isWatermarked()) {
      $formatString .= $this->configSuffixes['watermarked']['format'];
    }

    if ($this->isRetina()) {
      $formatString .= $this->configSuffixes['retina']['format'];
    }

    return $formatString;
  }

  public function getFormatSuffix() {
    $formatString = '';

    if ($this->isBlurred()) {
      $formatString .= $this->configSuffixes['blurred']['file'];
    } elseif ($this->isWatermarked()) {
      $formatString .= $this->configSuffixes['watermarked']['file'];
    }

    if ($this->isRetina()) {
      $formatString .= $this->configSuffixes['retina']['file'];
    }

    return $formatString;
  }

}
