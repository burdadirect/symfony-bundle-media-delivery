<?php

namespace HBM\TwigExtensionsBundle\Twig;

use HBM\ImageDeliveryBundle\Entity\Interfaces\Deliverable;
use HBM\ImageDeliveryBundle\Services\ImageDeliveryHelper;

class BaseUrlExtension extends \Twig_Extension
{

  /**
   * @var ImageDeliveryHelper
   */
  protected $imageDeliveryHelper;

  public function __construct(ImageDeliveryHelper $imageDeliveryHelper)
  {
    $this->imageDeliveryHelper = $imageDeliveryHelper;
  }

  public function getFilters()
  {
    return array(
      new \Twig_SimpleFilter('src', array($this, 'srcFilter')),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_imagedelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  public function srcFilter(Deliverable $image, $format, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->src($image, $format, $duration, $clientId, $clientSecret);
  }

}
