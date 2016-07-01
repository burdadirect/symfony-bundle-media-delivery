<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Video;
use HBM\MediaDeliveryBundle\Services\VideoDeliveryHelper;

class VideoDeliveryExtension extends \Twig_Extension
{

  /**
   * @var VideoDeliveryHelper
   */
  protected $videoDeliveryHelper;

  public function __construct(VideoDeliveryHelper $videoDeliveryHelper)
  {
    $this->videoDeliveryHelper = $videoDeliveryHelper;
  }

  public function getFilters()
  {
    return array(
      new \Twig_SimpleFilter('videoSrc', array($this, 'videoSrcFilter')),
    );
  }

  public function getName()
  {
    return 'hbm_twig_extensions_videodelivery';
  }

  /****************************************************************************/
  /* FILTERS                                                                  */
  /****************************************************************************/

  public function videoSrcFilter(Video $video, $encoding = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    if ($encoding === NULL) {
      $file = $video->getPath();
    } else {
      $file = $video->getPathFromEncoding($encoding);
    }
    return $this->videoDeliveryHelper->getSrc($video, $file, $duration, $clientId, $clientSecret);
  }

}
