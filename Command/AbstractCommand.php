<?php

namespace HBM\MediaDeliveryBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

abstract class AbstractCommand extends ContainerAwareCommand {

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
