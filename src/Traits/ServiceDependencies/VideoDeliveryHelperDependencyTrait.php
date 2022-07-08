<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\VideoDeliveryHelper;

trait VideoDeliveryHelperDependencyTrait {

  protected VideoDeliveryHelper $videoDeliveryHelper;

  /**
   * @required
   *
   * @param VideoDeliveryHelper $videoDeliveryHelper
   *
   * @return void
   */
  public function setVideoDeliveryHelper(VideoDeliveryHelper $videoDeliveryHelper): void {
    $this->videoDeliveryHelper = $videoDeliveryHelper;
  }

}
