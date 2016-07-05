<?php

namespace HBM\MediaDeliveryBundle\Entity\Interfaces;


interface Image {

  public function getId();

  public function getFile();

  public function getWidth();

  public function getHeight();

  public function getCredit();

  public function getFSK();

  /**
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\User|NULL $user
   * @return boolean
   */
  public function useWatermarkedFormat(User $user = NULL);

  /**
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\User|NULL $user
   * @return boolean
   */
  public function useBlurredFormat(User $user = NULL);

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
