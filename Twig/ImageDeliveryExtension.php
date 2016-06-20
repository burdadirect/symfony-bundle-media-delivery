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
      new \Twig_SimpleFilter('srcRatedForUser', array($this, 'srcRatedForUserFilter')),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_imagedelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  public function srcFilter(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->src($image, $format, $duration, $clientId, $clientSecret);
  }

  public function srcRatedFilter(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->srcRated($image, $format, $duration, $clientId, $clientSecret);
  }

  public function srcRatedForUserFilter(ImageDeliverable $image, UserReceivable $user = NULL, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    $this->imageDeliveryHelper->srcRatedForUser($image, $user, $format, $duration, $clientId, $clientSecret);
  }

}
