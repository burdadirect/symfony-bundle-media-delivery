<?php

namespace HBM\MediaDeliveryBundle\Service;

use HBM\HelperBundle\Service\HmacHelper;
use HBM\HelperBundle\Service\SanitizingHelper;
use HBM\MediaDeliveryBundle\HttpFoundation\CustomBinaryFileResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Service
 *
 * Makes image delivery easy.
 */
abstract class AbstractDeliveryHelper {

  /**
   * @var array
   */
  protected $config;

  /**
   * @var boolean
   */
  protected $debug;

  /****************************************************************************/

  /**
   * @var ParameterBagInterface
   */
  protected $parameterBag;

  /**
   * @var SanitizingHelper
   */
  protected $sanitizingHelper;

  /**
   * @var HmacHelper
   */
  protected $hmacHelper;

  /**
   * @var RouterInterface
   */
  protected $router;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * AbstractDeliveryHelper constructor.
   *
   * @param array $config
   * @param SanitizingHelper $sanitizingHelper
   * @param HmacHelper $hmacHelper
   * @param RouterInterface $router
   * @param LoggerInterface $logger
   */
  public function __construct(array $config, SanitizingHelper $sanitizingHelper, HmacHelper $hmacHelper, RouterInterface $router, LoggerInterface $logger) {
    $this->sanitizingHelper = $sanitizingHelper;
    $this->hmacHelper = $hmacHelper;
    $this->router = $router;
    $this->logger = $logger;

    $this->config = $config;
    $this->debug = $this->config['debug'] ?? FALSE;
  }

  /**
   * Calculates time and duration for hmac signature.
   * If duration is preceeded with a ~, an aproximated value is used.
   *
   * @param string|integer $duration
   * @return array
   */
  public function getTimeAndDuration($duration) : array {
    $time = time();

    $time_to_use = $time;
    $duration_to_use = $duration;
    $first_letter = $duration[0] ?? NULL;
    if ($first_letter === '~') {
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
      'time' => (int) $time_to_use,
      'duration' => (int) $duration_to_use
    ];
  }

  /**
   * @param $file
   * @param $statusCode
   * @param Request|NULL $request
   *
   * @return CustomBinaryFileResponse|Response
   */
  protected function serve($file, $statusCode, Request $request = NULL) {
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
      $headers['X-Accel-Buffering'] = 'no';
      return new CustomBinaryFileResponse($file, $statusCode, $headers);
    }

    $response = new CustomBinaryFileResponse($file, $statusCode, $headers);
    $response->prepare($request);

    return $response;
  }

}
