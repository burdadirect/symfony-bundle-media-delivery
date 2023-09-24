<?php

namespace HBM\MediaDeliveryBundle\Controller;

use HBM\MediaDeliveryBundle\HttpFoundation\CustomBinaryFileResponse;
use HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper;
use HBM\MediaDeliveryBundle\Service\VideoDeliveryHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends AbstractController
{
    /**
     * @throws \Exception
     *
     * @return CustomBinaryFileResponse|Response
     */
    public function serveImage(ImageDeliveryHelper $idh, Request $request, $format, $id, $file)
    {
        return $idh->dispatch(
            $format,
            $id,
            $file,
            $request
        );
    }

    /**
     * @return BinaryFileResponse|Response
     */
    public function serveVideo(VideoDeliveryHelper $vdh, Request $request, $id, $file)
    {
        return $vdh->dispatch(
            $id,
            $file,
            $request
        );
    }
}
