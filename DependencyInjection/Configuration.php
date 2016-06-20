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
        ->arrayNode('settings')->defaultValue(array())
          ->prototype('array')
            ->children()
              ->scalarNode('route')->defaultValue('imagecache')->end()
              ->scalarNode('duration')->defaultValue('~1200')->info('Number of seconds to expose the image. Can be prefixed with "~" to support caching.')->end()
            ->end()
          ->end()
        ->end()
        ->arrayNode('clients')->defaultValue(array())
          ->prototype('array')
            ->children()
              ->scalarNode('id')->end()
              ->scalarNode('secret')->end()
              ->scalarNode('default')->defaultFalse()->end()
            ->end()
          ->end()
        ->end()
        ->arrayNode('formats')->defaultValue(array())
          ->prototype('array')
            ->children()
              ->scalarNode('format')->end()
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
