<?php
namespace HBM\MediaDeliveryBundle\Services;

use HBM\HelperBundle\Services\HmacHelper;
use HBM\HelperBundle\Services\SanitizingHelper;
use HBM\MediaDeliveryBundle\HttpFoundation\CustomBinaryFileResponse;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;

/**
 * Service
 *
 * Makes image delivery easy.
 */
abstract class AbstractDeliveryHelper {

  /** @var array */
  protected $config;

  /** @var string */
  protected $env;

  /** @var boolean */
  protected $debug = FALSE;

  /** @var \HBM\HelperBundle\Services\SanitizingHelper */
  protected $sanitizingHelper;

  /** @var \HBM\HelperBundle\Services\HmacHelper */
  protected $hmacHelper;

  /** @var \Symfony\Component\Routing\Router */
  protected $router;

  /** @var \Symfony\Bridge\Monolog\Logger */
  protected $logger;

  public function __construct($config, SanitizingHelper $sanitizingHelper, HmacHelper $hmacHelper, Router $router, Logger $logger, $env = 'prod') {
    $this->config = $config;
    $this->sanitizingHelper = $sanitizingHelper;
    $this->hmacHelper = $hmacHelper;
    $this->router = $router;
    $this->logger = $logger;
    $this->env = $env;
    $this->debug = $this->config['debug'];
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

  protected function serve($file, $statusCode, Request $request) {
    if (!$file) {
      return new Response('', $statusCode);
    }

    $cacheSec = $this->config['settings']['cache'];
    $fileModificationTime = filemtime($file);

    if ($request === NULL) {
      $request = Request::createFromGlobals();
    }
    if ($request->headers->has('If-Modified-Since')) {
      $ifModifiedSinceHeader = strtotime($request->headers->get('If-Modified-Since'));

      if ($ifModifiedSinceHeader > $fileModificationTime) {
        $response = new Response();
        $response->setNotModified();
        return $response;
      }
    }

    $headers = [
      'Pragma' => 'private',
      'Cache-Control' => 'max-age='.$cacheSec,
      'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheSec),
      'Last-Modified' => gmdate('D, d M Y H:i:s', $fileModificationTime).' GMT',
      'Content-Type' => mime_content_type($file),
      'Content-Disposition' => 'inline; filename="'.basename($file).'"',
    ];


    /**************************************************************************/
    /* SERVE                                                                  */
    /**************************************************************************/

    if ($this->config['settings']['x_accel_redirect']) {
      $prefix = $this->sanitizingHelper->ensureSep($this->config['settings']['x_accel_redirect'], TRUE, TRUE);
      $path = $this->sanitizingHelper->ensureTrailingSep($this->config['folders']['cache']);
      $pathServed = str_replace($path, $prefix, $file);

      $headers['X-Accel-Redirect'] = $pathServed;
      return new CustomBinaryFileResponse($file, $statusCode, $headers);
    }

    $response = new CustomBinaryFileResponse($file, $statusCode, $headers);
    $response->prepare($request);

    return $response;
  }

}
