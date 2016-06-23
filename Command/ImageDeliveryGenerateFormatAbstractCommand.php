<?php

namespace HBM\ImageDeliveryBundle\Command;

use Imagine\Image\Palette\Grayscale;
use Imagine\Image\ImageInterface;
use Imagine\Image\BoxInterface;
use Imagine\Image\PointInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Box;
use Neutron\TemporaryFilesystem\IOException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Filesystem\Filesystem;

abstract class ImageDeliveryGenerateFormatAbstractCommand extends ContainerAwareCommand
{

  /**
   * @var Filesystem
   */
  private $filesystem;

  /**
   * @return Filesystem
   */
  protected function getFilesystem() {
    if ($this->filesystem === NULL) {
      $this->filesystem = new Filesystem();
    }

    return $this->filesystem;
  }

  protected function hbmMkdir($dir) {
    $this->getFilesystem()->mkdir($dir, 0775);
    try {
      $this->getFilesystem()->chown($dir, 'www-data', TRUE);
    } catch (IOException $e) {
    }
    try {
      $this->getFilesystem()->chgrp($dir, 'www-data', TRUE);
    } catch (IOException $e) {
    }
  }

  protected function hbmChmod($path) {
    $this->getFilesystem()->chmod($path, 0775, 0000, TRUE);
    try {
      $this->getFilesystem()->chown($path, 'www-data', TRUE);
    } catch (IOException $e) {
    }
    try {
      $this->getFilesystem()->chgrp($path, 'www-data', TRUE);
    } catch (IOException $e) {
    }
  }

  protected function enlargeResources() {
    //error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('memory_limit', '2G');

    if ($this->getContainer()->has('profiler')) {
      $this->getContainer()->get('profiler')->disable();
    }
  }

  /****************************************************************************/

  protected function generate($path_orig, $path_cache, $settings) {
    $filesystem = new Filesystem();

    // Make dir
    $pathinfo = pathinfo($path_cache);
    if (!$filesystem->exists($pathinfo['dirname'])) {
      $this->hbmMkdir($pathinfo['dirname']);
    }

    // Make image
    if ($settings['mode'] === 'crop') {
      if (isset($settings['clip'])) {
        $this->pbyCropCustom($path_orig, $path_cache, $settings);
      } else {
        $this->pbyCrop($path_orig, $path_cache, $settings);
      }
    } else {
      $this->pbyResize($path_orig, $path_cache, $settings);
    }
  }

  protected function handleColorProfiles(Image $image) {
    try {
      if ($image->getImagick()->getColorspace() === \Imagick::COLORSPACE_GRAY) {
        $image->usePalette(new Grayscale());
      } else {
        $image->usePalette(new RGB());
      }
      $image->getImagick()->stripimage();
      //$image->strip();
    } catch(\Exception $e) {
    }

    return $image;
  }

  protected function pbyResize($file_orig, $file_cached, $format) {
    $imagine = new Imagine();

    /** @var Image $image */
    $image = $imagine->open($file_orig);
    $image = $this->handleColorProfiles($image);


    if (substr($format['w'], -1) === '%') {
      $percent = floatval(substr($format['w'], 0, -1));
      $format['w'] = round($percent/100 * $image->getSize()->getWidth());
    }

    if (substr($format['h'], -1) === '%') {
      $percent = floatval(substr($format['h'], 0, -1));
      $format['h'] = round($percent/100 * $image->getSize()->getHeight());
    }

    $image = $this->pbyThumbnail($image, new PbyBox($format['w'], $format['h']), $format);

    // Apply effects
    if ($format['blur']) {
      $image = $this->pbyBlur($image, $file_cached, $format);
    } else {
      $image->effects()->sharpen(1);
    }

    if ($format['overlay']) {
      $image = $this->pbyOverlay($image, $file_cached, $format);
    }

    // Save options
    $optionsJPG = array(
      'resolution-unit' => ImageInterface::RESOLUTION_PIXELSPERINCH,
      'resolution-x' => 96,
      'resolution-y' => 96,
      'jpeg_quality' => $format['quality']['jpg'],
    );

    // Save options
    $optionsPNG = array(
      'resolution-unit' => ImageInterface::RESOLUTION_PIXELSPERINCH,
      'resolution-x' => 96,
      'resolution-y' => 96,
      'png_compression_level' => $format['quality']['png'],
    );

    $options = $optionsJPG;
    if ($format['mode'] === 'canvas') {
      $options = $optionsPNG;

      /** @var \Imagick $imagick */
      $imagick = $image->getImagick();

      $w = $format['w'];
      $h = $format['h'];
      if ($format['retina']) {
        $w = 2*$w;
        $h = 2*$h;
      }

      $x = ($w  - $imagick->getImageWidth()) / 2;
      $y = ($h - $imagick->getImageHeight()) / 2;

      $imagick->setimagebackgroundcolor('none');
      $imagick->extentimage($w, $h, -$x, -$y);
    }

    // Save image
    $image->save($file_cached, $options);
  }

  protected function pbyCropCustom($file_orig, $file_cached, $settings) {
    $imagine = new Imagine();

    $image = $imagine->open($file_orig);
    $image = $this->handleColorProfiles($image);

    $point = new Point($settings['clip']['x'], $settings['clip']['y']);
    $box = new PbyBox($settings['clip']['w'], $settings['clip']['h']);
    $image->crop($point, $box);

    if ($settings['retina']) {
      $box = new PbyBox(2*$settings['w'], 2*$settings['h']);
    } else {
      $box = new PbyBox($settings['w'], $settings['h']);
    }
    $image->resize($box);

    // Save options
    $options = array(
      'resolution-unit' => ImageInterface::RESOLUTION_PIXELSPERINCH,
      'resolution-x' => 96,
      'resolution-y' => 96,
      'jpeg_quality' => $settings['quality']['jpg'],
    );

    // Apply effects
    if ($settings['blur']) {
      $image = $this->pbyBlur($image, $file_cached, $settings);
    } else {
      $image->effects()->sharpen(1);
    }

    if ($settings['overlay']) {
      $image = $this->pbyOverlay($image, $file_cached, $settings);
    }

    // Save image
    $image->save($file_cached, $options);
  }

  protected function pbyCrop($file_orig, $file_cached, $format) {
    $imagine = new Imagine();

    $image = $imagine->open($file_orig);
    $image = $this->handleColorProfiles($image);

    $size = $image->getSize();
    $size = new PbyBox($size->getWidth(), $size->getHeight());

    // Check dimensions
    $resize = FALSE;
    if ($size->getHeight() < $format['h']) {
      $size = $size->heighten($format['h']);
      $resize = TRUE;
    }
    if ($size->getWidth() < $format['w']) {
      $size = $size->widen($format['w']);
      $resize = TRUE;
    }

    // Desired dimensions
    $box = new PbyBox($format['w'], $format['h']);

    // Ratios
    $ratioCrop = $format['w'] / $format['h'];
    $ratioImage = $size->getWidthFloat() / $size->getHeightFloat();

    if ($resize) {
      // Enlarge to minimum size
      $image->resize($size);
    } else {
      if ($ratioCrop > $ratioImage) {
        if ($format['retina']) {
          if ($size->getWidthFloat() >= 2*$format['w']) {
            $box = $box->widen(2*$format['w']);
          } elseif ($size->getWidthFloat() > $format['w']) {
            $box = $box->widen($size->getWidthFloat());
          }
        }
      } else {
        if ($format['retina']) {
          if ($size->getHeightFloat() >= 2*$format['h']) {
            $box = $box->heighten(2*$format['h']);
          } elseif ($size->getHeightFloat() > $format['h']) {
            $box = $box->heighten($size->getHeightFloat());
          }
        }
      }
    }

    // Determine scale
    $scale = new PbyBox($size->getWidth(), $size->getHeight());

    if ($ratioCrop > $ratioImage) {
      $scale = $scale->scale($box->getWidthFloat() / $size->getWidthFloat());
    } else {
      $scale = $scale->scale($box->getHeightFloat() / $size->getHeightFloat());
    }

    $image->resize($scale);

    // Determine crop point (gravity south or gravity center)
    if ($ratioCrop > $ratioImage) {
      $point = new Point(0, ($scale->getHeight() - $box->getHeightFloat())/4);
    } else {
      $point = new Point(($scale->getWidth() - $box->getWidthFloat())/2, 0);
    }

    $image->crop($point, $box);

    // Save options
    $options = array(
      'resolution-unit' => ImageInterface::RESOLUTION_PIXELSPERINCH,
      'resolution-x' => 96,
      'resolution-y' => 96,
      'jpeg_quality' => $format['quality']['jpg'],
    );

    // Apply effects
    if ($format['blur']) {
      $image = $this->pbyBlur($image, $file_cached, $format);
    } else {
      $image->effects()->sharpen(1);
    }

    if ($format['overlay']) {
      $image = $this->pbyOverlay($image, $file_cached, $format);
    }

    // Save image
    $image->save($file_cached, $options);
  }

  protected function pbyBlur(Image $image, $file_cached, $format) {
    $imageSize = $image->getSize();
    $squarePixels = $imageSize->square();

    $sqrt = sqrt($squarePixels);
    $radius = floor($sqrt / 100 * $format['blur']);
    $sigma = floor($radius / 2);

    /** @var \Imagick $imagick */
    $imagick = $image->getImagick();
    $imagick->blurImage($radius, $sigma);

    return $image;
  }

  protected function pbyOverlay(Image $image, $file_cached, $format) {
    $imagine = new Imagine();

    $overlay = $imagine->open($format['overlay']);
    $overlay = $this->handleColorProfiles($overlay);

    // Scale overlay
    $scale = '100%|100%';
    if (isset($format['oScale'])) {
      $scale = $format['oScale'];
    }
    $overlay = $this->pbyOverlayScale($image, $overlay, $scale);

    // Position overlay
    $gravity = 5;
    if (isset($format['oGravity'])) {
      $gravity = $format['oGravity'];
    }
    $overlayPoint = $this->pbyOverlayGravity($image, $overlay, $gravity);

    // Paste overlay
    $image->paste($overlay, $overlayPoint);

    return $image;
  }

  protected function pbyOverlayScale(Image $image, Image $overlay, $scale) {
    $imageSize = $image->getSize();

    if ($scale === 'inset') {
      $overlay = $this->pbyThumbnail($overlay, $imageSize);
    } elseif ($scale !== 'orig') {
      $parts = explode('|', $scale);

      $w = $this->pbyOverlayGeometry($parts[0]);
      $h = $this->pbyOverlayGeometry($parts[1]);
      $mode = $parts[2];

      $dim = $this->pbyOverlaySize($image, $overlay, $w, $h);

      if (!(($dim['w'] === NULL) && ($dim['h'] === NULL))) {
        $overlaySize = $overlay->getSize();

        if ($dim['w'] === NULL) {
          // Use height only
          if ($mode === '!') {
            $overlaySize = new Box($overlaySize->getWidth(), $dim['h']);
          } else {
            $overlaySize = $overlaySize->heighten($dim['h']);
          }
        } elseif ($dim['h'] === NULL) {
          // Use width only
          if ($mode === '!') {
            $overlaySize = new Box($dim['w'], $overlaySize->getHeight());
          } else {
            $overlaySize = $overlaySize->widen($dim['w']);
          }
        } else {
          // Use width and heigt
          if ($mode === '!') {
            $overlaySize = new Box($dim['w'], $dim['h']);
          } else {
            $overlaySize = new Box($dim['w'], $dim['h']);
          }
        }

        if ($mode === '!') {
          $overlay = $overlay->resize($overlaySize);
        } else {
          $overlay = $this->pbyThumbnail($overlay, $overlaySize);
        }
      }
    }

    return $overlay;
  }

  protected function pbyOverlaySize(Image $image, Image $overlay, $w, $h) {
    $imageSize = $image->getSize();
    $overlaySize = $overlay->getSize();

    $wNew = NULL;
    if ($w['value'] !== 'auto') {
      if ($w['unit'] === 'px') {
        $wNew = $this->pbyOverlayCalcScale($overlaySize->getWidth(), $w['value'], $w['scale']);
      } else {
        $wRelPixel = $this->pbyOverlayCalcSide($imageSize, $imageSize->getWidth(), $w['value'], $w['side']);
        $wNew = $this->pbyOverlayCalcScale($overlaySize->getWidth(), $wRelPixel, $w['scale']);
      }
    }

    $hNew = NULL;
    if ($h['value'] !== 'auto') {
      if ($h['unit'] === 'px') {
        $hNew = $this->pbyOverlayCalcScale($overlaySize->getHeight(), $h['value'], $h['scale']);
      } else {
        $hRelPixel = $this->pbyOverlayCalcSide($imageSize, $imageSize->getHeight(), $h['value'], $h['side']);
        $hNew = $this->pbyOverlayCalcScale($overlaySize->getHeight(), $hRelPixel, $h['scale']);
      }
    }

    return ['w' => $wNew, 'h' => $hNew];
  }

  protected function pbyOverlayCalcSide(BoxInterface $imageSize, $rel, $value, $mode) {
    if ($mode === '+') {
      $rel = max([$imageSize->getWidth(), $imageSize->getHeight()]);
    } elseif ($mode === '-') {
      $rel = min([$imageSize->getWidth(), $imageSize->getHeight()]);
    }

    return round($rel * $value / 100);
  }


  protected function pbyOverlayCalcScale($rel, $value, $mode) {
    if ($mode === '^') {
      return $value;
    } else {
      return min([$rel, $value]);
    }
  }

  protected function pbyOverlayGeometry($geometry) {
    // '<' = shrink / '>' = enlarge / '' = exact
    $scale = substr($geometry, 0, 1);
    if (!in_array($scale, ['^'])) {
      $scale = NULL;
    } else {
      $geometry = substr($geometry, 1);
    }

    // '+' = use long side / '-' = short side / '' = width
    $side = substr($geometry, -1);
    if (!in_array($side, ['+', '-'])) {
      $side = NULL;
    } else {
      $geometry = substr($geometry, 0, -1);
    }

    // Get unit of values
    $unit = substr($geometry, -1);
    if ($unit === '%') {
      $geometry = substr($geometry, 0, -1);
    } else {
      $unit = substr($geometry, -2);
      if ($unit === 'px') {
        $geometry = substr($geometry, 0, -2);
      } else {
        $unit = 'px';
      }
    }

    return [
      'value' => $geometry,
      'unit'  => $unit,
      'scale' => $scale,
      'side'  => $side,
    ];
  }

  protected function pbyOverlayGravity(Image $image, Image $overlay, $gravity) {
    $imageSize = $image->getSize();
    $overlaySize = $overlay->getSize();

    $watermarkPoint = new Point(
      ($imageSize->getWidth() - $overlaySize->getWidth())/2,
      ($imageSize->getHeight() - $overlaySize->getHeight())/2
    );

    switch ($gravity) {
      case 1:
        // top left
        $watermarkPoint = new Point(
          0,
          0
        );
        break;
      case 2:
        // top center
        $watermarkPoint = new Point(
          ($imageSize->getWidth() - $overlaySize->getWidth())/2,
          0
        );
        break;
      case 3:
        // top right
        $watermarkPoint = new Point(
          $imageSize->getWidth() - $overlaySize->getWidth(),
          0
        );
        break;
      case 4:
        // center left
        $watermarkPoint = new Point(
          0,
          ($imageSize->getHeight() - $overlaySize->getHeight())/2
        );
        break;
      case 6:
        // center right
        $watermarkPoint = new Point(
          $imageSize->getWidth() - $overlaySize->getWidth(),
          ($imageSize->getHeight() - $overlaySize->getHeight())/2
        );
        break;
      case 7:
        // bottom left
        $watermarkPoint = new Point(
          0,
          $imageSize->getHeight() - $overlaySize->getHeight()
        );
        break;
      case 8:
        // bottom center
        $watermarkPoint = new Point(
          ($imageSize->getWidth() - $overlaySize->getWidth())/2,
          $imageSize->getHeight() - $overlaySize->getHeight()
        );
        break;
      case 9:
        // bottom right
        $watermarkPoint = new Point(
          $imageSize->getWidth() - $overlaySize->getWidth(),
          $imageSize->getHeight() - $overlaySize->getHeight()
        );
        break;
    }

    return $watermarkPoint;
  }

  protected function pbyThumbnail(Image $image, BoxInterface $box, $format = []) {
    $size = $image->getSize();

    if (isset($format['retina']) && $format['retina']) {
      $box = $box->scale(2);
    }

    // Ratios
    $ratioResize = $box->getWidth() / $box->getHeight();
    $ratioImage = $size->getWidth() / $size->getHeight();

    $scale = new PbyBox($size->getWidth(), $size->getHeight());
    if ($ratioResize > $ratioImage) {
      $scale = $scale->scale($box->getHeight() / $size->getHeight());
    } else {
      $scale = $scale->scale($box->getWidth() / $size->getWidth());
    }

    // Resize (shrink and enlarge) or thumbnail (shrink only)
    if (isset($format['mode']) && ($format['mode'] === 'thumbnail')) {
      $image = $image->thumbnail($scale, ImageInterface::THUMBNAIL_INSET);
    } else {
      $image->resize($scale);
    }

    return $image;
  }

}

class PbyBox implements BoxInterface
{
  /**
   * @var float
   */
  private $width;

  /**
   * @var float
   */
  private $height;

  /**
   * Constructs the Size with given width and height
   *
   * @param float $width
   * @param float $height
   *
   * @throws \InvalidArgumentException
   */
  public function __construct($width, $height)
  {
    $this->width  = $width;
    $this->height = $height;
  }

  public function getWidth()
  {
    return round($this->width);
  }

  public function getWidthFloat()
  {
    return $this->width;
  }

  public function getHeight()
  {
    return round($this->height);
  }

  public function getHeightFloat()
  {
    return $this->height;
  }

  public function scale($ratio)
  {
    return new PbyBox($ratio * $this->width, $ratio * $this->height);
  }

  public function increase($size)
  {
    return new PbyBox((float) $size + $this->width, (float) $size + $this->height);
  }

  public function contains(BoxInterface $box, PointInterface $start = null)
  {
    $start = $start ? $start : new Point(0, 0);

    return $start->in($this) && $this->width >= $box->getWidth() + $start->getX() && $this->height >= $box->getHeight() + $start->getY();
  }

  public function square()
  {
    return $this->width * $this->height;
  }

  public function __toString()
  {
    return sprintf('%dx%d px', $this->width, $this->height);
  }

  public function widen($width)
  {
    return $this->scale($width / $this->width);
  }

  public function heighten($height)
  {
    return $this->scale($height / $this->height);
  }
}
