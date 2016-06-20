<?php

namespace HBM\ImageDeliveryBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractCommand extends ContainerAwareCommand
{

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * @return Filesystem
   */
  protected function getFilesystem() {
    if ($this->filesystem === NULL) {
      $this->filesystem = new Filesystem();
    }

    return $this->filesystem;
  }

  protected function hbmMkdir($dir) {
    $this->getFilesystem()->mkdir($dir, 0775);
    try {
      $this->getFilesystem()->chown($dir, 'www-data', TRUE);
    } catch (IOException $e) {
    }
    try {
      $this->getFilesystem()->chgrp($dir, 'www-data', TRUE);
    } catch (IOException $e) {
    }
  }

  protected function hbmChmod($path) {
    $this->getFilesystem()->chmod($path, 0775, 0000, TRUE);
    try {
      $this->getFilesystem()->chown($path, 'www-data', TRUE);
    } catch (IOException $e) {
    }
    try {
      $this->getFilesystem()->chgrp($path, 'www-data', TRUE);
    } catch (IOException $e) {
    }
  }

  protected function enlargeResources() {
    //error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('memory_limit', '2G');

    if ($this->getContainer()->has('profiler')) {
      $this->getContainer()->get('profiler')->disable();
    }
  }


}
