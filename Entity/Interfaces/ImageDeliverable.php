<?php

namespace HBM\ImageDeliveryBundle\Entity\Interfaces;


interface ImageDeliverable {

  public function getId();

  public function getFile();

  public function getWidth();

  public function getHeight();

  public function getFSK();

  public function getWatermark();

  public function getCredit();

  /**
   * Returns true if an image is rated and it's not the appropriate time.
   *
   * @param null $datetime
   * @return bool
   */
  public function isCurrentlyRated(\DateTime $datetime = NULL);

  /**
   * Get clipping for a certain format.
   *
   * @param string $format
   * @return array:
   */
  public function getClipping($format);

  /**
   * Determines if a format hat a custom clipping.
   *
   * @param string $format
   * @return boolean
   */
  public function hasClipping($format);

}
