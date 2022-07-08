<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper;

trait ImageDeliveryHelperDependencyTrait {

  protected ImageDeliveryHelper $imageDeliveryHelper;

  /**
   * @required
   *
   * @param ImageDeliveryHelper $imageDeliveryHelper
   *
   * @return void
   */
  public function setImageDeliveryHelper(ImageDeliveryHelper $imageDeliveryHelper): void {
    $this->imageDeliveryHelper = $imageDeliveryHelper;
  }

}
