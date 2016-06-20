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

  public function getClipping();

  public function hasClipping($format);

  public function isCurrentlyRated();

}
