<?php

namespace HBM\MediaDeliveryBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HBMMediaDeliveryBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
