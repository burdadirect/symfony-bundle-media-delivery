<?php

namespace HBM\ImageDeliveryBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class HBMImageDeliveryExtension extends Extension {

  /**
   * {@inheritdoc}
   */
  public function load(array $configs, ContainerBuilder $container) {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);

    $container->setParameter('hbm.image_delivery', $config);
    $container->setParameter('hbm.image_delivery.settings.route', $config['settings']['route']);
    $container->setParameter('hbm.image_delivery.formats', $config['formats']);
    $container->setParameter('hbm.image_delivery.clients', $config['clients']);
    $container->setParameter('hbm.image_delivery.exif', $config['exif']);

    $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.yml');
  }
}
