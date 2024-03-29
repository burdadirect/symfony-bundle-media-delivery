<?php

namespace HBM\MediaDeliveryBundle\Twig;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Video;
use HBM\MediaDeliveryBundle\Service\VideoDeliveryHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class VideoDeliveryExtension extends AbstractExtension
{
    protected VideoDeliveryHelper $vdh;

    /**
     * VideoDeliveryExtension constructor.
     */
    public function __construct(VideoDeliveryHelper $videoDeliveryHelper)
    {
        $this->vdh = $videoDeliveryHelper;
    }

    public function getFilters(): array
    {
        return [
          new TwigFilter('videoSrc', $this->videoSrcFilter(...)),
        ];
    }

    /* FILTERS */

    /**
     * @param null $encoding
     * @param null $duration
     * @param null $clientId
     * @param null $clientSecret
     *
     * @throws \Exception
     */
    public function videoSrcFilter(Video $video, $encoding = null, $duration = null, $clientId = null, $clientSecret = null): string
    {
        if ($encoding === null) {
            $file = $video->getPath();
        } else {
            $file = $video->getPathFromEncoding($encoding);
        }

        return $this->vdh->getSrc($video, $file, $duration, $clientId, $clientSecret);
    }
}
