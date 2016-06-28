<?php
namespace HBM\MediaDeliveryBundle\Services;

use HBM\HelperBundle\Services\HmacHelper;
use HBM\HelperBundle\Services\SanitizingHelper;
use HBM\MediaDeliveryBundle\Command\GenerateCommand;
use HBM\MediaDeliveryBundle\Entity\Interfaces\VideoDeliverable;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Service
 *
 * Makes video delivery easy.
 */
class VideoDeliveryHelper extends AbstractDeliveryHelper {

  /** @var array */
  private $config;

  /** @var string */
  private $env;

  /** @var boolean */
  private $debug = TRUE;

  /** @var \HBM\HelperBundle\Services\SanitizingHelper */
  private $sanitizingHelper;

  /** @var \HBM\HelperBundle\Services\HmacHelper */
  private $hmacHelper;

  /** @var \Symfony\Component\Routing\Router */
  private $router;

  /** @var \Symfony\Bridge\Monolog\Logger */
  private $logger;

  public function __construct($config, SanitizingHelper $sanitizingHelper, HmacHelper $hmacHelper, Router $router, Logger $logger, $env = 'prod') {
    $this->config = $config;
    $this->sanitizingHelper = $sanitizingHelper;
    $this->hmacHelper = $hmacHelper;
    $this->router = $router;
    $this->logger = $logger;
    $this->env = $env;
  }

  /**
   * Returns an image url.
   *
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\VideoDeliverable $video
   * @param string|integer|NULL $duration
   * @param string|NULL $clientId
   * @param string|NULL $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrc(VideoDeliverable $video, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
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

    // DURATION
    $durationToUse = $duration;
    if ($durationToUse === NULL) {
      $durationToUse = $this->config['settings']['duration'];
    }

    // TIME AND DURATION
    $timeAndDuration = $this->getTimeAndDuration($durationToUse);

    // FILE
    $file = $this->sanitizingHelper->ensureSep($video->getPath(), FALSE);

    $signature = $this->getSignature(
      $file,
      $video->getId(),
      $timeAndDuration['time'],
      $timeAndDuration['duration'],
      $clientIdToUse,
      $clientSecretToUse
    );

    $paramsQuery = [
      'sig' => $signature,
      'ts' => $timeAndDuration['time'],
      'sec' => $timeAndDuration['duration'],
      'client' => $clientIdToUse
    ];

    $paramsRoute = [
      'id' => $video->getId(),
      'file' => $file,
    ];

    return $this->router->generate('hbm_video_delivery_src', $paramsRoute).'?'.http_build_query($paramsQuery);
  }

  /**
   * Assemble string to sign for an image.
   *
   * @param $file
   * @param $id
   * @param $time
   * @param $duration
   * @param $clientId
   * @param $clientSecret
   * @return string
   */
  public function getSignature($file, $id, $time, $duration, $clientId, $clientSecret) {
    $stringToSign = $file."\n";
    $stringToSign .= $id."\n";
    $stringToSign .= $time."\n";
    $stringToSign .= $duration."\n";
    $stringToSign .= $clientId."\n";

    return $this->hmacHelper->sign($stringToSign, $clientSecret);
  }

  /**
   * Dispatches a specific format for an image.
   *
   * @param string $format
   * @param string|int $id
   * @param string $file
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function dispatch($format, $id, $file, Request $request = NULL) {
    if ($request === NULL) {
      $request = Request::createFromGlobals();
    }

    /**************************************************************************/
    /* VARIABLES                                                              */
    /**************************************************************************/

    $folders = $this->config['folders'];
    $clients = $this->config['clients'];
    $fallbacks = $this->config['fallbacks'];

    $query = $request->query->all();

    ini_set('memory_limit', $this->config['settings']['memory_limit']);

    $dirOrig = $this->sanitizingHelper->normalizeFolderAbsolute($folders['orig']);


    /**************************************************************************/
    /* CHECK PARAMS                                                           */
    /**************************************************************************/

    // ROUTE PARAMS
    $invalidRequest = FALSE;
    if ($id === NULL) {
      if ($this->debug) {
        $this->logger->error('ID is null.');
      }
      $invalidRequest = TRUE;
    }
    if ($file === NULL) {
      if ($this->debug) {
        $this->logger->error('File is null.');
      }
      $invalidRequest = TRUE;
    }

    // QUERY PARAMS
    $keys = ['ts', 'sec', 'client', 'sig'];
    foreach ($keys as $key) {
      if (!isset($query[$key])) {
        if ($this->debug) {
          $this->logger->error('Query param "'.$key.'" is missing.');
        }
        $invalidRequest = TRUE;
      }
    }

    if ($invalidRequest) {
      return $this->serve($fallbacks['412'], 412, $request);
    }


    /******************************************************************************/
    /* CHECK ACCESS                                                               */
    /******************************************************************************/

    $access = TRUE;
    if (!isset($clients[$query['client']])) {
      if ($this->debug) {
        $this->logger->error('Client is missing.');
      }
      $access = FALSE;
    } elseif ($query['sig'] !== $this->getSignature($file, $id, $query['ts'], $query['sec'], $query['client'], $clients[$query['client']]['secret'])) {
      if ($this->debug) {
        $this->logger->error('Signature is invalid.');
      }
      $access = FALSE;
    } elseif (($query['ts'] + $query['sec']) < time()) {
      if ($this->debug) {
        $this->logger->error('Timestamp is too old.');
      }
      $access = FALSE;
    }

    if (!$access) {
      return $this->serve($fallbacks['403'], 403, $request);
    }

    /******************************************************************************/
    /* CHECK FOR FILE                                                             */
    /******************************************************************************/

    $fileOrig = $dirOrig.$file;

    if (file_exists($fileOrig)) {
      return $this->serve($fileOrig, 200, $request);
    } else {
      if ($this->debug) {
        $this->logger->error('Orig file can not be found.');
      }
      return $this->serve($fallbacks['404'], 404, $request);
    }
  }

  /**
   * Serves (and generats) a specific format for an image.
   *
   * @param string|null $file
   * @param integer $statusCode
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function serve($file, $statusCode, Request $request = NULL) {
    if (!$file) {
      return new Response('', $statusCode);
    }

    /**************************************************************************/
    /* HEADER                                                                 */
    /**************************************************************************/

    $cacheSec = $this->config['settings']['cache'];
    $fileModificationTime = filemtime($file);

    if ($request === NULL) {
      $request = Request::createFromGlobals();
    }
    if ($request->headers->has('If-Modified-Since')) {
      $ifModifiedSinceHeader = strtotime($request->headers->get('If-Modified-Since'));

      if ($ifModifiedSinceHeader > $fileModificationTime) {
        $response = new Response();
        $response->setNotModified(TRUE);
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
      return new BinaryFileResponse($file, $statusCode, $headers);
    }

    return new BinaryFileResponse($file, $statusCode, $headers);
  }

}
