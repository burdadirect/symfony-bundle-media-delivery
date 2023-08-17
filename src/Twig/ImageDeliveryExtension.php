<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Entity\Interfaces\User;
use HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageDeliveryExtension extends AbstractExtension {

  protected ImageDeliveryHelper $idh  ;

  /**
   * ImageDeliveryExtension constructor.
   *
   * @param ImageDeliveryHelper $imageDeliveryHelper
   */
  public function __construct(ImageDeliveryHelper $imageDeliveryHelper) {
    $this->idh = $imageDeliveryHelper;
  }

  /**
   * @return array|TwigFilter[]
   */
  public function getFilters(): array {
    return [
      new TwigFilter('imgSrc', $this->imgSrcFilter(...)),
    ];
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
    $formatObj = $this->idh->createFormatObjFromString($format);
    if ($formatObj->getFormat() === $format) {
      return $this->idh->getSrc($image, $user, $format, $retina, $blurred, $watermarked, $duration, $clientId, $clientSecret);
    }

    return $this->idh->getSrc($image, $user, $formatObj->getFormat(), $formatObj->isRetina(), $formatObj->isBlurred(), $formatObj->isWatermarked(), $duration, $clientId, $clientSecret);
  }

}
