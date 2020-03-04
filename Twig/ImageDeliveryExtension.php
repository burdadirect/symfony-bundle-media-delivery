<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Entity\Interfaces\User;
use HBM\MediaDeliveryBundle\Services\ImageDeliveryHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageDeliveryExtension extends AbstractExtension
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
    return [
      new TwigFilter('imgSrc', [$this, 'imgSrcFilter']),
    ];
  }

  public function getName()
  {
    return 'hbm_twig_extensions_imagedelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  /**
   * @param Image $image
   * @param null $format
   * @param User|NULL $user
   * @param bool $retina
   * @param null $blurred
   * @param null $watermarked
   * @param null $duration
   * @param null $clientId
   * @param null $clientSecret
   *
   * @return string
   *
   * @throws \Exception
   */
  public function imgSrcFilter(Image $image, $format = NULL, User $user = NULL, $retina = FALSE, $blurred = NULL, $watermarked = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) : string {
    $formatObj = $this->imageDeliveryHelper->createFormatObjFromString($format);
    if ($formatObj->getFormat() === $format) {
      return $this->imageDeliveryHelper->getSrc($image, $user, $format, $retina, $blurred, $watermarked, $duration, $clientId, $clientSecret);
    }

    return $this->imageDeliveryHelper->getSrc($image, $user, $formatObj->getFormat(), $formatObj->isRetina(), $formatObj->isBlurred(), $formatObj->isWatermarked(), $duration, $clientId, $clientSecret);
  }

}
