<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper;
use Symfony\Contracts\Service\Attribute\Required;

trait ImageDeliveryHelperDependencyTrait {

  protected ImageDeliveryHelper $imageDeliveryHelper;

  /**
   * @param ImageDeliveryHelper $imageDeliveryHelper
   *
   * @return void
   */
  #[Required]
  public function setImageDeliveryHelper(ImageDeliveryHelper $imageDeliveryHelper): void {
    $this->imageDeliveryHelper = $imageDeliveryHelper;
  }

}
