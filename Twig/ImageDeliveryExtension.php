<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable;
use HBM\MediaDeliveryBundle\Entity\Interfaces\UserReceivable;
use HBM\MediaDeliveryBundle\Services\ImageDeliveryHelper;

class ImageDeliveryExtension extends \Twig_Extension
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
      new \Twig_SimpleFilter('imgSrc', array($this, 'imgSrcFilter')),
      new \Twig_SimpleFilter('imgSrcRated', array($this, 'imgSrcRatedFilter')),
      new \Twig_SimpleFilter('imgSrcRatedForUser', array($this, 'imgSrcRatedForUserFilter')),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_imagedelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  public function imgSrcFilter(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrc($image, $format, $duration, $clientId, $clientSecret);
  }

  public function imgSrcRatedFilter(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrcRated($image, $format, $duration, $clientId, $clientSecret);
  }

  public function imgSrcRatedForUserFilter(ImageDeliverable $image, UserReceivable $user = NULL, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrcRatedForUser($image, $user, $format, $duration, $clientId, $clientSecret);
  }

}
