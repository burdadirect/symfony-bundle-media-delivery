<?php
namespace HBM\ImageDeliveryBundle\Services;

use HBM\HelperBundle\Services\HmacHelper;
use HBM\HelperBundle\Services\SanitizingHelper;
use HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable;
use HBM\ImageDeliveryBundle\Entity\Interfaces\UserReceivable;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * Service
 *
 * Makes image delivery easy.
 */
class ImageDeliveryHelper {

  /**
   * @var array
   */
  private $config;

  /** @var \HBM\HelperBundle\Services\SanitizingHelper */
  private $sanitizingHelper;

  /** @var \HBM\HelperBundle\Services\HmacHelper */
  private $hmacHelper;

  /** @var \Symfony\Component\Routing\Router */
  private $router;

  /** @var \Symfony\Bridge\Monolog\Logger */
  private $logger;

  public function __construct($config, SanitizingHelper $sanitizingHelper, HmacHelper $hmacHelper, Router $router, Logger $logger) {
    $this->config = $config;
    $this->sanitizingHelper = $sanitizingHelper;
    $this->hmacHelper = $hmacHelper;
    $this->router = $router;
    $this->logger = $logger;
  }

  /**
   * Returns an image url.
   *
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param string|NULL $format
   * @param string|integer|NULL $duration
   * @param string|NULL $clientId
   * @param string|NULL $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrc(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    // CLIENT ID
    $clientIdToUse = $clientId;
    if ($clientIdToUse === NULL) {
      foreach ($this->config['clients'] as $clientKey => $clientConfig) {
        if ($clientConfig['default']) {
          $clientIdToUse = $clientKey;
        }
      }
    }

    // CLIENT SECRET
    $clientSecretToUse = $clientSecret;
    if ($clientSecretToUse === NULL) {
      if (!isset($this->config['clients'][$clientIdToUse])) {
        throw new \Exception('Client "'.$clientIdToUse.'" not found.');
      }

      $clientSecretToUse = $this->config['clients'][$clientIdToUse]['secret'];
    }

    // FORMAT
    $formatToUse = $format;
    if ($formatToUse === NULL) {
      foreach ($this->config['formats'] as $formatKey => $formatConfig) {
        if ($formatConfig['default']) {
          $formatToUse = $formatKey;
        }
      }
    }

    // DURATION
    $durationToUse = $duration;
    if ($durationToUse === NULL) {
      $durationToUse = $this->config['settings']['duration'];
    }

    $formatToUsePlain = $this->getFormatPlain($formatToUse);
    if (!isset($this->config['formats'][$formatToUsePlain])) {
      throw new \Exception('Format "'.$formatToUsePlain.'" not found.');
    }
    $formatConfigToUse = $this->config['formats'][$formatToUsePlain];

    // TIME AND DURATION
    $timeAndDuration = $this->getTimeAndDuration($durationToUse);

    // FILE
    $file = $this->sanitizingHelper->ensureSep($image->getFile(), FALSE);

    // CUSTOM
    $custom = intval($image->hasClipping($formatToUsePlain));

    $signature = $this->getSignature(
      $file,
      $image->getId(),
      $timeAndDuration['time'],
      $timeAndDuration['duration'],
      $formatToUse,
      $custom,
      $clientIdToUse,
      $clientSecretToUse
    );

    $paramsQuery = [
      'sig' => $signature,
      'ts' => $timeAndDuration['time'],
      'sec' => $timeAndDuration['duration'],
      'client' => $clientIdToUse,
      'custom' => $custom,
    ];

    $paramsRoute = [
      'format' => $formatToUse,
      'id' => $image->getId(),
      'file' => $file,
    ];

    $url = $this->router->generate('hbm_image_delivery_src', $paramsRoute);
    if ($formatConfigToUse['restricted']) {
      $url .= '?'.http_build_query($paramsQuery);
    }

    return $url;
  }

  /**
   * Returns an image url, depending of image settings.
   *
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param string|NULL $format
   * @param string|integer|NULL $duration
   * @param string|NULL $clientId
   * @param string|NULL $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrcRated(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    return $this->getSrc($image, $this->getFormatAdjusted($image, $format), $duration, $clientId, $clientSecret);
  }

  /**
   * Returns an image url, depending on user settings.
   *
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\UserReceivable|NULL $user
   * @param string|NULL $format
   * @param string|integer|NULL $duration
   * @param string|NULL $clientId
   * @param string|NULL $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrcRatedForUser(ImageDeliverable $image, UserReceivable $user = NULL, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    if ($user && $user->getNoFsk() && ($image->getFsk() < 21)) {
      return $this->getSrc($image, $format, $duration, $clientId, $clientSecret);
    } else {
      return $this->getSrcRated($image, $format, $duration, $clientId, $clientSecret);
    }
  }

  /**
   * Calculates time and duration for hmac signature.
   * If duration is preceeded with a ~, an aproximated value is used.
   *
   * @param string|integer $duration
   * @return array
   */
  public function getTimeAndDuration($duration) {
    $time = time();

    $time_to_use = $time;
    $duration_to_use = $duration;
    if (substr($duration, 0, 1) === '~') {
      $duration_to_use = substr($duration, 1);

      $time_to_use = $time;
      if ($duration_to_use > 100000) {
        $time_to_use = round($time / 10000) * 10000;
      } elseif ($duration_to_use > 10000) {
        $time_to_use = round($time / 1000) * 1000;
      } elseif ($duration_to_use > 1000) {
        $time_to_use = round($time / 100) * 100;
      } elseif ($duration_to_use > 100) {
        $time_to_use = round($time / 10) * 10;
      }
    }

    return [
      'time' => intval($time_to_use),
      'duration' => intval($duration_to_use)
    ];
  }

  /**
   * Check for retina/blurred/watermarked format
   *
   * @param $format
   * @return string
   */
  public function getFormatPlain($format) {
    $formatOrig = $format;
    if (substr($formatOrig, -7) === '-retina') {
      $formatOrig = substr($formatOrig, 0, -7);
    }
    if (substr($formatOrig, -8) === '-blurred') {
      $formatOrig = substr($formatOrig, 0, -8);
    }
    if (substr($formatOrig, -12) === '-watermarked') {
      $formatOrig = substr($formatOrig, 0, -12);
    }

    return $formatOrig;
  }

  /**
   * Check for retina/blurred/watermarked format
   *
   * @param $format
   * @return string
   */
  public function getFormatSuffix($format) {
    $formatSuffix = '';

    if (substr($format, -8) === '-blurred') {
      $formatSuffix = '_blurred';
      $format = substr($format, 0, -8);
    } elseif (substr($format, -12) === '-watermarked') {
      $formatSuffix = '_watermarked';
      $format = substr($format, 0, -12);
    }

    if (substr($format, -7) === '-retina') {
      $formatSuffix = $formatSuffix.'__retina';
    }

    return $formatSuffix;
  }

  /**
   * Check for retina/blurred/watermarked format.
   *
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param $format
   * @return string
   */
  public function getFormatAdjusted(ImageDeliverable $image, $format) {
    $formatsToWatermark = [];
    foreach ($this->config['formats'] as $formatKey => $formatConfig) {
      if ($formatConfig['watermark']) {
        $formatsToWatermark[] = $formatKey;
      }
    }

    if ($image->isCurrentlyRated()) {
      $format .= '-blurred';
    } elseif ($image->getWatermark() && in_array($format, $formatsToWatermark)) {
      $format .= '-watermarked';
    }

    return $format;
  }

  /**
   * Assemble string to sign for an image.
   *
   * @param $file
   * @param $id
   * @param $time
   * @param $duration
   * @param $format
   * @param $custom
   * @param $clientId
   * @param $clientSecret
   * @return string
   */
  public function getSignature($file, $id, $time, $duration, $format, $custom, $clientId, $clientSecret) {
    $stringToSign = $file."\n";
    $stringToSign .= $id."\n";
    $stringToSign .= $time."\n";
    $stringToSign .= $duration."\n";
    $stringToSign .= $format."\n";
    $stringToSign .= $custom."\n";
    $stringToSign .= $clientId."\n";

    return $this->hmacHelper->sign($stringToSign, $clientSecret);
  }

  public function getFileCache($file, $format) {
    $pathinfo = pathinfo($file);

    $formatPlain = $this->getFormatPlain($format);
    $formatSuffix = $this->getFormatSuffix($format);

    $formatConfig = [];
    if (isset($this->config['formats'][$formatPlain])) {
      $formatConfig = $this->config['formats'][$formatPlain];
    }

    $folderCache = $this->sanitizingHelper->normalizeFolderRelative($formatPlain.$formatSuffix);
    if ($pathinfo['dirname'] !== '.') {
      $folderCache .= $this->sanitizingHelper->normalizeFolderRelative($pathinfo['dirname']);
    }

    $fileCache = $pathinfo['filename'].'.jpg';

    // Use png as extension if needed.
    if (isset($formatConfig['type'])) {
      if (($formatConfig['type'] === 'png') && (substr($fileCache, -4) === '.jpg')) {
        $fileCache = substr($fileCache, 0, -4).'.png';
      } elseif (($formatConfig['type'] === 'png') && (substr($fileCache, -5) === '.jpeg')) {
        $fileCache = substr($fileCache, 0, -5).'.png';
      }
    }

    return $folderCache.$fileCache;
  }

}
