<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Entity\Interfaces\User;
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

  public function imgSrcFilter(Image $image, $format = NULL, $retina = FALSE, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrc($image, $format, $retina, $duration, $clientId, $clientSecret);
  }

  public function imgSrcRatedFilter(Image $image, $format = NULL, $retina = FALSE, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrcRated($image, $format, $retina, $duration, $clientId, $clientSecret);
  }

  public function imgSrcRatedForUserFilter(Image $image, User $user = NULL, $format = NULL, $retina = FALSE, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->imageDeliveryHelper->getSrcRatedForUser($image, $user, $format, $retina, $duration, $clientId, $clientSecret);
  }

}
