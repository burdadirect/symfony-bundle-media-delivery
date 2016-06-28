<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\VideoDeliverable;
use HBM\MediaDeliveryBundle\Services\VideoDeliveryHelper;

class ImageDeliveryExtension extends \Twig_Extension
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

  public function videoSrcFilter(VideoDeliverable $video, $duration = NULL, $clientId = NULL, $clientSecret = NULL)
  {
    return $this->videoDeliveryHelper->getSrc($video, $duration, $clientId, $clientSecret);
  }

}
