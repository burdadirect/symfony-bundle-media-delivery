<?php

namespace HBM\ImageDeliveryBundle\Twig;

use HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable;
use HBM\ImageDeliveryBundle\Entity\Interfaces\UserReceivable;
use HBM\ImageDeliveryBundle\Services\ImageDeliveryHelper;

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
      new \Twig_SimpleFilter('src', array($this, 'srcFilter')),
      new \Twig_SimpleFilter('srcRated', array($this, 'srcRatedFilter')),
      new \Twig_SimpleFilter('srcRatedUser', array($this, 'srcRatedUserFilter')),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_imagedelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  public function srcFilter(ImageDeliverable $image, $format, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->src($image, $format, $duration, $clientId, $clientSecret);
  }

  public function srcRatedFilter(ImageDeliverable $image, $format, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->srcRated($image, $format, $duration, $clientId, $clientSecret);
  }

  public function srcRatedUserFilter(ImageDeliverable $image, UserReceivable $user, $format, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->srcRatedForUser($user, $image, $format, $duration, $clientId, $clientSecret);
  }

}
