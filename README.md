# HBM Image Delivery Bundle

## Team

### Developers
Christian Puchinger - puchinger@burda.com

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

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new HBM\ImageDeliveryBundle\HBMImageDeliveryBundle(),
        );

        // ...
    }

    // ...
}
```

### Configuration

```yml
hbm_image_delivery:
    settings:
        route:    "imagecache"
        duration: "~1200"
    formats:
        orig:               { w: 100%, h: 100%, quality: { jpg: 95, png: 9 }, exif: 1, watermark: 0, restricted: 1, type: png, mode: resize }
        full:               { w: 2500, h: 2500, quality: { jpg: 90, png: 8 }, exif: 1, watermark: 1, restricted: 1, type: jpg, mode: thumbnail }
        gallery:            { w: 1500, h: 1500, quality: { jpg: 90, png: 8 }, exif: 1, watermark: 1, restricted: 1, type: jpg, mode: thumbnail }
        thumb:              { w: 500,  h: 500,  quality: { jpg: 80, png: 7 }, exif: 0, watermark: 0, restricted: 0, type: jpg, mode: thumbnail }
        thumb-square-trans: { w: 500,  h: 500,  quality: { jpg: 80, png: 7 }, exif: 0, watermark: 0, restricted: 0, type: png, mode: canvas }
    clients:
        playboy:
            id:      playboy
            secret:  xyz
            default: true
        opengraph:
            id:      opengraph
            secret:  xyz
        google:
            id:      google
            secret:  xyz
```
