<?php

namespace HBM\ImageDeliveryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder();

    /** @var NodeDefinition $rootNode */
    $rootNode = $treeBuilder->root('hbm_image_delivery');

    $rootNode
      ->children()
        ->scalarNode('translation_domain')->defaultFalse()->end()
        ->arrayNode('folders')
          ->children()
            ->scalarNode('orig')->end()
            ->scalarNode('cache')->end()
          ->end()
        ->end()
        ->arrayNode('images')
          ->children()
            ->scalarNode('blurred')->end()
            ->scalarNode('watermark')->end()
            ->scalarNode('400')->end()
            ->scalarNode('403')->end()
            ->scalarNode('412')->end()
          ->end()
        ->end()
        ->arrayNode('settings')->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('route')->defaultValue('imagecache')->end()
            ->scalarNode('duration')->defaultValue('~1200')->info('Number of seconds to expose the image. Can be prefixed with "~" to support caching.')->end()
            ->scalarNode('cache')->defaultValue(86400)->info('Number of seconds set the cache header to.')->end()
            ->scalarNode('memory_limit')->defaultValue('512M')->info('Increase memory limit to support handling of large images.')->end()
          ->end()
        ->end()
        ->arrayNode('x-sendfile')
          ->children()
            ->scalarNode('path')->end()
            ->scalarNode('prefix')->end()
          ->end()
        ->end()
        ->arrayNode('x-accel-redirect')
          ->children()
            ->scalarNode('path')->end()
            ->scalarNode('prefix')->end()
          ->end()
        ->end()
        ->arrayNode('clients')->defaultValue(array())->useAttributeAsKey('id')
          ->prototype('array')
            ->children()
              ->scalarNode('id')->end()
              ->scalarNode('secret')->end()
              ->scalarNode('default')->defaultFalse()->end()
            ->end()
          ->end()
        ->end()
        ->arrayNode('formats')->defaultValue(array())->useAttributeAsKey('name')
          ->prototype('array')
            ->children()
              ->scalarNode('format')->end()
              ->scalarNode('default')->defaultFalse()->end()
              ->scalarNode('w')->defaultValue(1500)->info('Can be pixel or percent.')->end()
              ->scalarNode('h')->defaultValue(1500)->info('Can be pixel or percent.')->end()
              ->scalarNode('type')->defaultValue('jpg')->info('Can be "jpg" or "png".')->end()
              ->scalarNode('mode')->defaultValue('thumbnail')->info('Can be "thumbnail", "crop", "resize" or "canvas".')->end()
              ->scalarNode('watermark')->defaultFalse()->info('These formats should be watermarked.')->end()
              ->scalarNode('restricted')->defaultTrue()->info('These formats should be delivered only when hash is valid.')->end()
              ->arrayNode('quality')
                ->children()
                  ->scalarNode('jpg')->defaultValue(80)->info('Can be between 0 and 100.')->end()
                  ->scalarNode('png')->defaultValue(7)->info('Can be between 0 and 10.')->end()
                ->end()
              ->end()
              ->scalarNode('exif')->defaultValue(0)->info('Needs "exiftool" to be installed.')->end()
            ->end()
          ->end()
        ->end()
      ->end()
    ->end();

    return $treeBuilder;
  }

}
