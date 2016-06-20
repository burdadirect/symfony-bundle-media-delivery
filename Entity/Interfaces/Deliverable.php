<?php

namespace HBM\ImageDeliveryBundle\Entity\Interfaces;


interface Deliverable {

  public function getId();

  public function getFile();

  public function getWidth();

  public function getHeight();

  public function getFSK();

  public function getCredit();

  public function getClipping();

  public function hasClipping($format);

}
