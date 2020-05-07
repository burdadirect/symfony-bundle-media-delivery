# HBM Image Delivery Bundle

## Team

### Developers
Christian Puchinger - christian.puchinger@burda.com

## Installation

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require burdanews/image-delivery-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

With Symfony 4 the bundle is enabled automatically for all environments (see `config/bundles.php`).

### Step 3: Configuration

```yml
hbm_media_delivery:
    video_delivery:
        settings:
            entity_name: "AcmeBundle:Video"
            x_accel_redirect: 'protected-videos'
    
        folders:
            orig: '%dir_videos%'
    
        clients:
            - { id: "acme",  secret: "xyz",  default: true }


    image_delivery:
        settings:
            entity_name: "AcmeBundle:Image"
            route:       "protected-images"
            duration:    "~1200"

        folders:
            orig: '%dir_images%'
            cache: '%dir_images_cache%'
            
        fallbacks:
            403: '%kernel.root_dir%/path/to/fallback_image_403.png'
            404: '%kernel.root_dir%/path/to/fallback_image_404.png'
            412: '%kernel.root_dir%/path/to/fallback_image_412.png'
            
        clients:
            playboy:
                id:      acme
                secret:  xyz
                default: true
            opengraph:
                id:      opengraph
                secret:  xyz
            google:
                id:      google
                secret:  xyz

        overlays:
            blurred:
                file: '%kernel.root_dir%/path/to/overlay_blurred.png'
            watermarked:
                file: '%kernel.root_dir%/path/to/overlay_watermarked.png'

        formats:
            orig:               { w: 100%, h: 100%, quality: { jpg: 95, png: 9 }, exif: 1, watermark: 0, restricted: 1, type: png, mode: resize }
            full:               { w: 2500, h: 2500, quality: { jpg: 90, png: 8 }, exif: 1, watermark: 1, restricted: 1, type: jpg, mode: thumbnail }
            gallery:            { w: 1500, h: 1500, quality: { jpg: 90, png: 8 }, exif: 1, watermark: 1, restricted: 1, type: jpg, mode: thumbnail }
            thumb:              { w: 500,  h: 500,  quality: { jpg: 80, png: 7 }, exif: 0, watermark: 0, restricted: 0, type: jpg, mode: thumbnail }
            thumb-square-trans: { w: 500,  h: 500,  quality: { jpg: 80, png: 7 }, exif: 0, watermark: 0, restricted: 0, type: png, mode: canvas }

        exif:
            company:       "..."
            company_short: "..."
            product:       "..."
            url:           "..."
            notice:        "..."
            email:         "..."
            telephone:     "..."
            street:        "..."
            city:          "..."
            zip:           "..."
            region:        "..."
            country:       "..."
            country_code:   "..."

```

## Optimization

If you want to further improve delivery performance, get rid of the bootstrapping and create a redirect to a static php file.
An example of the php file can be found under `Resources/public/image.php` or `Resources/public/video.php`. 

```nginx

    # Rewrite for images
    location /imagecache {
        rewrite ^/imagecache/(.*?)/(.*?)/(.*)$ /image.php?image-format=$1&image-id=$2&image-path=$3 last;
    }
```

### Custom kernel boot file

```
<?php

use APP\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';

umask(0002);

if ($_SERVER['APP_DEBUG']) {
  umask(0000);

  Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
  Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
  Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

/** @var \HBM\MediaDeliveryBundle\Service\ImageDeliveryHelper $imageDeliveryHelper */
$imageDeliveryHelper = $kernel->getContainer()->get('hbm.helper.image_delivery');

$response = $imageDeliveryHelper->dispatch(
  $_GET['image-format'] ?? NULL,
  $_GET['image-id'] ?? NULL,
  $_GET['image-path'] ?? NULL
);

$response->send();

// OR
// /** @var VideoDeliveryHelper $videoDeliveryHelper */
// $videoDeliveryHelper = $kernel->getContainer()->get('hbm.helper.video_delivery');
// 
// $response = $videoDeliveryHelper->dispatch(
//   $_GET['video-id'],
//   $_GET['video-path']
// );
// 
// $response->send();
```
