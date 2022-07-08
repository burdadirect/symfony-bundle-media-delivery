<?php

namespace HBM\MediaDeliveryBundle\Controller;

use HBM\MediaDeliveryBundle\HttpFoundation\CustomBinaryFileResponse;
use HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper;
use HBM\MediaDeliveryBundle\Service\VideoDeliveryHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends AbstractController {

  private ImageDeliveryHelper $idh;

  private VideoDeliveryHelper $vdh;

  /**
   * DeliveryController constructor.
   *
   * @param ImageDeliveryHelper $idh
   * @param VideoDeliveryHelper $vdh
   */
  public function __construct(ImageDeliveryHelper $idh, VideoDeliveryHelper $vdh) {
    $this->idh = $idh;
    $this->vdh = $vdh;
  }

  /**
   * @param Request $request
   * @param $format
   * @param $id
   * @param $file
   * @return CustomBinaryFileResponse|Response
   * @throws \Exception
   */
  public function serveImageAction(Request $request, $format, $id, $file) {
    return $this->idh->dispatch(
      $format,
      $id,
      $file,
      $request
    );
  }

  /**
   * @param Request $request
   * @param $id
   * @param $file
   * @return BinaryFileResponse|Response
   */
  public function serveVideoAction(Request $request, $id, $file) {
    return $this->vdh->dispatch(
      $id,
      $file,
      $request
    );
  }

}
