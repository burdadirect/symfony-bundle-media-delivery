<?php
namespace HBM\MediaDeliveryBundle\Services;

use HBM\HelperBundle\Services\HmacHelper;
use HBM\HelperBundle\Services\SanitizingHelper;
use HBM\MediaDeliveryBundle\Command\GenerateCommand;
use HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable;
use HBM\MediaDeliveryBundle\Entity\Interfaces\UserReceivable;
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
 * Makes image delivery easy.
 */
class ImageDeliveryHelper extends AbstractDeliveryHelper {

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
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
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
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
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
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\UserReceivable|NULL $user
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
   * Get default format.
   *
   * @return string
   */
  public function getFormatDefault() {
    foreach ($this->config['formats'] as $formatKey => $formatConfig) {
      if ($formatConfig['default']) {
        return $formatKey;
      }
    }

    return 'thumb';
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
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
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

  /**
   * Dispatches a specific format for an image.
   *
   * @param string $format
   * @param string|int $id
   * @param string $file
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @param \Symfony\Component\HttpKernel\Kernel|NULL $kernel
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function dispatch($format, $id, $file, Request $request = NULL, Kernel $kernel = NULL) {
    if ($request === NULL) {
      $request = Request::createFromGlobals();
    }

    /**************************************************************************/
    /* VARIABLES                                                              */
    /**************************************************************************/

    $folders = $this->config['folders'];
    $formats = $this->config['formats'];
    $fallbacks = $this->config['fallbacks'];
    $overlays = $this->config['overlays'];
    $clients = $this->config['clients'];

    $query = $request->query->all();

    ini_set('memory_limit', $this->config['settings']['memory_limit']);

    $dirOrig = $this->sanitizingHelper->normalizeFolderAbsolute($folders['orig']);
    $dirCache = $this->sanitizingHelper->normalizeFolderAbsolute($folders['cache']);


    /**************************************************************************/
    /* CHECK PARAMS                                                           */
    /**************************************************************************/

    $arguments = GenerateCommand::determineArguments($format, $overlays);

    $formatPlain = $this->getFormatPlain($format);

    // ROUTE PARAMS
    $invalidRequest = FALSE;
    if ($format === NULL) {
      if ($this->debug) {
        $this->logger->error('Format is null.');
      }
      $invalidRequest = TRUE;
    }
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
    $keys = ['ts', 'sec', 'client', 'sig', 'custom'];
    foreach ($keys as $key) {
      if (!isset($query[$key])) {
        if ($this->debug) {
          $this->logger->error('Query param "'.$key.'" is missing.');
        }
        $invalidRequest = TRUE;
      }
    }

    if (!isset($formats[$formatPlain])) {
      if ($this->debug) {
        $this->logger->error('Format is invalid.');
      }
      $invalidRequest = TRUE;
    }

    if ($invalidRequest) {
      $formatDefault = $this->getFormatDefault();

      return $this->generateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $fallbacks['412'],
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['412']), $formatDefault),
        '--custom'   => false
      ], $arguments), 412, $request, $kernel);
    }


    /******************************************************************************/
    /* CHECK ACCESS                                                               */
    /******************************************************************************/

    $access = TRUE;
    if ($formats[$formatPlain]['restricted']) {
      if (!isset($clients[$query['client']])) {
        if ($this->debug) {
          $this->logger->error('Client is missing.');
        }
        $access = FALSE;
      } elseif ($query['sig'] !== $this->getSignature($file, $id, $query['ts'], $query['sec'], $format, $query['custom'], $query['client'], $clients[$query['client']]['secret'])) {
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
    }

    if (!$access) {
      return $this->generateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $fallbacks['403'],
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['403']), $format),
        '--custom'   => false,
      ], $arguments), 403, $request, $kernel);
    }

    /******************************************************************************/
    /* CHECK FOR FILE                                                             */
    /******************************************************************************/

    $fileOrig = $dirOrig.$file;
    $fileCache = $dirCache.$this->getFileCache($file, $format);

    if (file_exists($fileOrig)) {
      return $this->generateAndServe(array_merge([
        'image'      => $id,
        'path-orig'  => $fileOrig,
        'path-cache' => $fileCache,
        '--custom'   => $query['custom'],
      ], $arguments), 200, $request, $kernel);
    } else {
      if ($this->debug) {
        $this->logger->error('Orig file can not be found.');
      }
      return $this->generateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $fallbacks['404'],
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['404']), $format),
        '--custom'   => false,
      ], $arguments), 404, $request, $kernel);
    }
  }

  /**
   * Serves (and generats) a specific format for an image.
   *
   * @param $arguments
   * @param integer $statusCode
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @param \Symfony\Component\HttpKernel\Kernel|NULL $kernel
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function generateAndServe($arguments, $statusCode, Request $request = NULL, Kernel $kernel = NULL) {
    $file = $arguments['path-cache'];

    /**************************************************************************/
    /* GENERATE                                                               */
    /**************************************************************************/

    if (!file_exists($file)) {
      $command = ['command' => GenerateCommand::name];
      $arguments = array_merge($command, $arguments);

      if ($kernel === NULL) {
        $kernel = new \AppKernel($this->env, false);
      }

      $application = new Application($kernel);
      $application->add(new GenerateCommand());
      $application->setAutoExit(FALSE);

      $input  = new ArrayInput($arguments);
      $output = new BufferedOutput();

      $command = $application->find('hbm:image-delivery:generate');
      $command->run($input, $output);
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
