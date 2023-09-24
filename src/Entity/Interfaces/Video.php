<?php

namespace HBM\MediaDeliveryBundle\Entity\Interfaces;

interface Video
{
    public function getId();

    public function getPath();

    public function getPathFromEncoding($encoding);
}
