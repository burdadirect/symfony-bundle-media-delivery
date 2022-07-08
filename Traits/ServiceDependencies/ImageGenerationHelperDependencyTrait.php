<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\ImageGenerationHelper;

trait ImageGenerationHelperDependencyTrait {

  protected ImageGenerationHelper $imageGenerationHelper;

  /**
   * @required
   *
   * @param ImageGenerationHelper $imageGenerationHelper
   *
   * @return void
   */
  public function setImageGenerationHelper(ImageGenerationHelper $imageGenerationHelper): void {
    $this->imageGenerationHelper = $imageGenerationHelper;
  }

}
