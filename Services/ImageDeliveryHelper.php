<?php
namespace HBM\ImageDeliveryBundle\Services;

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

  /** @var \Symfony\Component\Routing\Router */
  private $router;

  /** @var \Symfony\Bridge\Monolog\Logger */
  private $logger;

  public function __construct($config, Router $router, Logger $logger) {
    $this->config = $config;
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
  public function src(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    // CLIENT ID
    $clientIdToUse = $clientId;
    if ($clientId === NULL) {
      foreach ($this->config['clients'] as $clientConfig) {
        if ($clientConfig['default']) {
          $clientIdToUse = $clientConfig['id'];
        }
      }
    }

    // CLIENT SECRET
    $clientSecretToUse = $clientSecret;
    if ($clientSecret === NULL) {
      if (!isset($this->config['clients'][$clientId])) {
        throw new \Exception('Client "'.$clientId.'" not found.');
      }

      $clientSecretToUse = $this->config['clients'][$clientId]['secret'];
    }

    // FORMAT
    $formatToUse = $format;
    if ($format === NULL) {
      foreach ($this->config['formats'] as $formatKey => $formatConfig) {
        if ($formatConfig['default']) {
          $formatToUse = $formatKey;
        }
      }
    }

    if (!isset($this->config['formats'][$formatToUse])) {
      throw new \Exception('Format "'.$formatToUse.'" not found.');
    }
    $formatConfigToUse = $this->config['formats'][$formatToUse];

    // TIME AND DURATION
    $timeAndDuration = $this->getTimeAndDuration($duration);

    // FILE
    $file = ltrim($image->getFile(), '/');

    // CUSTOM
    $custom = $image->hasClipping($this->formatPlain($formatToUse));

    $signature = $this->getSignature(
      $file,
      $image->getId(),
      $timeAndDuration['time'],
      $timeAndDuration['duration'],
      $formatToUse,
      intval($custom),
      $clientIdToUse,
      $clientSecretToUse
    );

    $paramsQuery = [
      'sig' => $signature,
      'ts' => $timeAndDuration['time'],
      'sec' => $timeAndDuration['duration'],
      'client' => $clientId,
      'custom' => intval($custom),
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
  public function srcRated(ImageDeliverable $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    return $this->src($image, $this->formatAdjusted($image, $format), $duration, $clientId, $clientSecret);
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
  public function srcRatedForUser(ImageDeliverable $image, UserReceivable $user = NULL, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    if ($user && $user->getNoFsk() && ($image->getFsk() < 21)) {
      return $this->src($image, $format, $duration, $clientId, $clientSecret);
    } else {
      return $this->srcRated($image, $format, $duration, $clientId, $clientSecret);
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

    return array('time' => $time_to_use, 'duration' => $duration_to_use);
  }

  /**
   * Check for retina/blurred/watermarked format
   *
   * @param $format
   * @return string
   */
  public function formatPlain($format) {
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
   * Check for retina/blurred/watermarked format.
   *
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param $format
   * @return string
   */
  public function formatAdjusted(ImageDeliverable $image, $format) {
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

    return base64_encode(hash_hmac('sha256', $stringToSign, $clientSecret, true));
  }

}
