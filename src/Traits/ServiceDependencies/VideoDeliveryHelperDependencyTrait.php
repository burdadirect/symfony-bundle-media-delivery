<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\VideoDeliveryHelper;
use Symfony\Contracts\Service\Attribute\Required;

trait VideoDeliveryHelperDependencyTrait {

  protected VideoDeliveryHelper $videoDeliveryHelper;

  /**
   * @param VideoDeliveryHelper $videoDeliveryHelper
   *
   * @return void
   */
  #[Required]
  public function setVideoDeliveryHelper(VideoDeliveryHelper $videoDeliveryHelper): void {
    $this->videoDeliveryHelper = $videoDeliveryHelper;
  }

}
