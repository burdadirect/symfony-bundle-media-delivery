<?php

namespace HBM\MediaDeliveryBundle\Traits\ServiceDependencies;

use HBM\MediaDeliveryBundle\Service\ImageGenerationHelper;
use Symfony\Contracts\Service\Attribute\Required;

trait ImageGenerationHelperDependencyTrait
{
    protected ImageGenerationHelper $imageGenerationHelper;

    #[Required]
    public function setImageGenerationHelper(ImageGenerationHelper $imageGenerationHelper): void
    {
        $this->imageGenerationHelper = $imageGenerationHelper;
    }
}
