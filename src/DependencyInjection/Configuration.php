<?php

namespace HBM\MediaDeliveryBundle\DependencyInjection;

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
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('hbm_media_delivery');
        $rootNode    = $treeBuilder->getRootNode();

        $overlayGravityInfo = 'Can be an integer between 1 and 9. ';
        $overlayGravityInfo .= '1: top left, 2: top center, 3: top right, ';
        $overlayGravityInfo .= '4: center left, 5: center center, 6: center right, ';
        $overlayGravityInfo .= '7: bottom left, 8: bottom center, 9: bottom right';

        $overlayScaleInfo = 'Can be inset, orig or a custom scale string. ';
        $overlayScaleInfo .= 'The custom scale string has the following form: W|H|mode. ';
        $overlayScaleInfo .= 'W/H can be: MVUS. ';
        $overlayScaleInfo .= 'M (mode) can be: "^" = allow upscale / "" = exact ';
        $overlayScaleInfo .= 'V (value) can be: any integer or "auto". ';
        $overlayScaleInfo .= 'U (unit) can be: "%" or "px". ';
        $overlayScaleInfo .= 'S (side) can be: "+" = long side / "-" = short side / "" = same side.';

        $rootNode
          ->children()
            ->scalarNode('translation_domain')->defaultFalse()->end()
            ->scalarNode('debug')->defaultFalse()->end()

            // VIDEO DELIVERY
            ->arrayNode('video_delivery')
              ->children()
                ->append($this->getClientConfig())
                ->append($this->getSettingsConfig('video-delivery', '~1200', 86400, '512M'))
                ->append($this->getFallbackConfig())
                ->arrayNode('folders')
                  ->children()
                    ->scalarNode('orig')->end()
                  ->end()
                ->end()
              ->end()
            ->end()

            // IMAGE DELIVERY
            ->arrayNode('image_delivery')
              ->children()
                ->append($this->getClientConfig())
                ->append($this->getSettingsConfig('image-delivery', '~1200', 86400, '512M'))
                ->append($this->getFallbackConfig())
                ->arrayNode('folders')
                  ->children()
                    ->scalarNode('orig')->end()
                    ->scalarNode('cache')->end()
                  ->end()
                ->end()
                ->arrayNode('overlays')->addDefaultsIfNotSet()
                  ->children()
                    ->append($this->getOverlayConfig('blurred', 5, '', 5, $overlayGravityInfo, '100%%|100%%|', 'Scale to fit. ' . $overlayScaleInfo))
                    ->append($this->getOverlayConfig('watermarked', 0, '', 9, $overlayGravityInfo, '30%%+|auto|', 'Use 30% of long side for width, scale height according to aspect ratio. ' . $overlayScaleInfo))
                  ->end()
                ->end()
                ->arrayNode('suffixes')->addDefaultsIfNotSet()
                  ->children()
                    ->append($this->getSuffixConfig('blurred', '-blurred', '_blurred'))
                    ->append($this->getSuffixConfig('watermarked', '-watermarked', '_watermarked'))
                    ->append($this->getSuffixConfig('retina', '-retina', '__retina'))
                   ->end()
                ->end()
                ->arrayNode('formats')->defaultValue([])->useAttributeAsKey('name')
                  ->prototype('array')
                    ->children()
                      ->scalarNode('format')->end()
                      ->scalarNode('default')->defaultFalse()->end()
                      ->scalarNode('w')->defaultValue(1500)->info('Can be pixel or percent.')->end()
                      ->scalarNode('h')->defaultValue(1500)->info('Can be pixel or percent.')->end()
                      ->scalarNode('type')->defaultValue('jpg')->info('Can be "jpg" or "png".')->end()
                      ->scalarNode('mode')->defaultValue('thumbnail')->info('Can be "thumbnail", "crop", "resize" or "canvas".')->end()
                      ->scalarNode('blurred')->defaultTrue()->info('These formats should be blurred.')->end()
                      ->scalarNode('watermarked')->defaultFalse()->info('These formats should be watermarked.')->end()
                      ->scalarNode('restricted')->defaultTrue()->info('These formats should be delivered only when hash is valid.')->end()
                      ->arrayNode('quality')
                        ->children()
                          ->scalarNode('jpg')->defaultValue(80)->info('Can be between 0 and 100.')->end()
                          ->scalarNode('png')->defaultValue(7)->info('Can be between 0 and 10.')->end()
                        ->end()
                      ->end()
                      ->arrayNode('optimizations')->defaultValue([])
                        ->prototype('scalar')->end()
                      ->end()
                      ->scalarNode('exif')->defaultValue(0)->info('Needs "exiftool" to be installed.')->end()
                    ->end()
                  ->end()
                ->end()
                ->arrayNode('optimizations')->defaultValue([])->useAttributeAsKey('name')
                  ->prototype('array')
                    ->children()
                      ->scalarNode('path')->end()
                      ->arrayNode('options')->defaultValue([])
                        ->prototype('scalar')->end()
                      ->end()
                    ->end()
                  ->end()
                ->end()
                ->arrayNode('exif')->addDefaultsIfNotSet()
                  ->children()
                    ->scalarNode('company')->defaultValue('')->end()
                    ->scalarNode('company_short')->defaultValue('')->end()
                    ->scalarNode('product')->defaultValue('')->end()
                    ->scalarNode('url')->defaultValue('')->end()
                    ->scalarNode('notice')->defaultValue('')->end()
                    ->scalarNode('email')->defaultValue('')->end()
                    ->scalarNode('telephone')->defaultValue('')->end()
                    ->scalarNode('contact')->defaultValue('')->end()
                    ->scalarNode('street')->defaultValue('')->end()
                    ->scalarNode('city')->defaultValue('')->end()
                    ->scalarNode('zip')->defaultValue('')->end()
                    ->scalarNode('region')->defaultValue('')->end()
                    ->scalarNode('country')->defaultValue('')->end()
                    ->scalarNode('country_code')->defaultValue('')->end()
                  ->end()
                ->end()
              ->end()
            ->end()
          ->end()
        ->end();

        return $treeBuilder;
    }

    private function getOverlayConfig($overlayName, $blurDefault, $blurInfo, $gravityDefault, $gravityInfo, $scaleDefault, $scaleInfo)
    {
        $builder = new TreeBuilder($overlayName);
        $node    = $builder->getRootNode();

        $node
          ->children()
            ->scalarNode('blur')->defaultValue($blurDefault)->info($blurInfo)->end()
            ->scalarNode('file')->isRequired()->end()
            ->scalarNode('gravity')->defaultValue($gravityDefault)->info($gravityInfo)->end()
            ->scalarNode('scale')->defaultValue($scaleDefault)->info($scaleInfo)->end()
          ->end();

        return $node;
    }

    private function getClientConfig()
    {
        $builder = new TreeBuilder('clients');
        $node    = $builder->getRootNode();

        $node
          ->defaultValue([])->useAttributeAsKey('id')
          ->prototype('array')
            ->children()
              ->scalarNode('id')->end()
              ->scalarNode('secret')->end()
              ->scalarNode('default')->defaultFalse()->end()
            ->end()
          ->end();

        return $node;
    }

    private function getSettingsConfig($routeDefault, $durationDefault, $cacheDefault, $memoryLimitDefault)
    {
        $builder = new TreeBuilder('settings');
        $node    = $builder->getRootNode();

        $node
          ->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('entity_name')->end()
            ->scalarNode('entity_callable')->end()
            ->arrayNode('entity_names')
              ->useAttributeAsKey('name')
              ->prototype('scalar')->end()
            ->end()
            ->arrayNode('entity_callables')
              ->useAttributeAsKey('name')
              ->prototype('scalar')->end()
            ->end()
            ->scalarNode('entity_id_separator')->defaultValue('-')->end()
            ->scalarNode('route')->defaultValue($routeDefault)->end()
            ->scalarNode('duration')->defaultValue($durationDefault)->info('Number of seconds to expose the media file. Can be prefixed with "~" to support caching.')->end()
            ->scalarNode('cache')->defaultValue($cacheDefault)->info('Number of seconds set the cache header to.')->end()
            ->scalarNode('memory_limit')->defaultValue($memoryLimitDefault)->info('Increase memory limit to support handling of large media files.')->end()
            ->scalarNode('x_accel_redirect')->defaultValue('')->info('Improves delivery performance on nginx servers.')->end()
          ->end();

        return $node;
    }

    private function getFallbackConfig()
    {
        $builder = new TreeBuilder('fallbacks');
        $node    = $builder->getRootNode();

        $node
          ->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('403')->defaultNull()->end()
            ->scalarNode('404')->defaultNull()->end()
            ->scalarNode('412')->defaultNull()->end()
          ->end();

        return $node;
    }

    private function getSuffixConfig($suffix, $suffixFormat, $suffixFile)
    {
        $builder = new TreeBuilder($suffix);
        $node    = $builder->getRootNode();

        $node
          ->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('format')->defaultValue($suffixFormat)->end()
            ->scalarNode('file')->defaultValue($suffixFile)->end()
          ->end();

        return $node;
    }
}
