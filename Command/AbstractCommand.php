<?php

namespace HBM\MediaDeliveryBundle\Command;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class AbstractCommand extends Command {

  /**
   * @var ParameterBagInterface
   */
  protected $pb;

  /**
   * @var ObjectManager
   */
  protected $om;

  /**
   * AbstractCommand constructor.
   *
   * @param ParameterBagInterface $pb
   * @param ObjectManager $om
   */
  public function __construct(ParameterBagInterface $pb, ObjectManager $om) {
    parent::__construct();

    $this->pb = $pb;
    $this->om = $om;
  }

  protected function enlargeResources() : void {
    //error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('memory_limit', '2G');

    if ($this->getContainer()->has('profiler')) {
      $this->getContainer()->get('profiler')->disable();
    }
  }

}
