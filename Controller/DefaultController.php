<?php

namespace HBM\MediaDeliveryBundle\Controller;

use HBM\MediaDeliveryBundle\Services\ImageDeliveryHelper;
use HBM\MediaDeliveryBundle\Services\VideoDeliveryHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{

  public function serveImageAction(Request $request, $format, $id, $file)
  {
    return $this->getImageDeliveryHelper()->dispatch(
      $format,
      $id,
      $file,
      $request,
      $this->get('kernel')
    );
  }

  public function serveVideoAction(Request $request, $id, $file)
  {
    return $this->getVideoDeliveryHelper()->dispatch(
      $id,
      $file,
      $request
    );
  }

  /**
   * @return ImageDeliveryHelper
   */
  private function getImageDeliveryHelper() {
    return $this->get('hbm.helper.image_delivery');
  }

  /**
   * @return VideoDeliveryHelper
   */
  private function getVideoDeliveryHelper() {
    return $this->get('hbm.helper.video_delivery');
  }

}
