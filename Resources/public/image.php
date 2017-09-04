<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Debug\Debug;
use HBM\MediaDeliveryBundle\Services\ImageDeliveryHelper;

// if you don't want to setup permissions the proper way, just uncomment the following PHP line
// read http://symfony.com/doc/current/book/installation.html#configuration-and-setup for more information
// umask(0002);

/**
 * @var Composer\Autoload\ClassLoader $loader
 */
$loader = require __DIR__.'/../app/autoload.php';
include_once __DIR__.'/../var/bootstrap.php.cache';

$env = getenv('SYMFONY_ENV') ?: 'dev';
$debug = getenv('SYMFONY_DEBUG') !== '0' && $env !== 'prod';

if ($debug) {
  Debug::enable();
}

$kernel = new AppKernel($env, $debug);
$kernel->boot();

/** @var ImageDeliveryHelper $imageDeliveryHelper */
$imageDeliveryHelper = $kernel->getContainer()->get('hbm.helper.image_delivery');

$response = $imageDeliveryHelper->dispatch(
  $_GET['image-format'] ?? NULL,
  $_GET['image-id'] ?? NULL,
  $_GET['image-path'] ?? NULL,
  NULL,
  $kernel
);

$response->send();
