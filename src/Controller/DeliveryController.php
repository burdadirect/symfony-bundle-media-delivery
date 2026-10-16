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
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function serveImage(ImageDeliveryHelper $idh, Request $request, string $format, string $id, string $file): CustomBinaryFileResponse|Response
    {
        return $idh->dispatch($format, $id, $file, $request);
    }

    public function serveVideo(VideoDeliveryHelper $vdh, Request $request, string $id, string $file): BinaryFileResponse|Response
    {
        return $vdh->dispatch($id, $file, $request);
    }
}
