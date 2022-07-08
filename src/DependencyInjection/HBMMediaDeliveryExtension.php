<?php

namespace HBM\MediaDeliveryBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class HBMMediaDeliveryExtension extends Extension {

  /**
   * {@inheritdoc}
   */
  public function load(array $configs, ContainerBuilder $container): void {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);

    $configToUse = $config['video_delivery'];
    $container->setParameter('hbm.video_delivery', $configToUse);
    $container->setParameter('hbm.video_delivery.settings.route', $configToUse['settings']['route']);
    $container->setParameter('hbm.video_delivery.clients', $configToUse['clients']);

    $configToUse = $config['image_delivery'];
    $container->setParameter('hbm.image_delivery', $configToUse);
    $container->setParameter('hbm.image_delivery.settings.route', $configToUse['settings']['route']);
    $container->setParameter('hbm.image_delivery.clients', $configToUse['clients']);
    $container->setParameter('hbm.image_delivery.formats', $configToUse['formats']);
    $container->setParameter('hbm.image_delivery.optimizations', $configToUse['optimizations']);
    $container->setParameter('hbm.image_delivery.exif', $configToUse['exif']);

    $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
    $loader->load('services.yaml');
  }
}
