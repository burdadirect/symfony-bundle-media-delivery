<?php

namespace HBM\MediaDeliveryBundle\Services;

use HBM\MediaDeliveryBundle\Command\GenerateCommand;
use HBM\MediaDeliveryBundle\Entity\Format;
use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Entity\Interfaces\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Service
 *
 * Makes image delivery easy.
 */
class ImageDeliveryHelper extends AbstractDeliveryHelper {

  /** @var string  */
  private $formatDefault;

  /** @var array */
  private $formatsBlurred;

  /** @var array */
  private $formatsWatermarked;

  /**
   * Returns an image url.
   *
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\Image $image
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\User $user
   * @param null $format
   * @param bool $retina
   * @param null $watermarked
   * @param null $blurred
   * @param null $duration
   * @param null $clientId
   * @param null $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrc(Image $image, User $user = NULL, $format = NULL, $retina = FALSE, $blurred = NULL, $watermarked = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
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
      $formatToUse = $this->getFormatDefault();
    }

    // FORMAT OBJECT
    $formatObj = new Format($formatToUse, $this->config['suffixes']);
    if (!$formatObj instanceof Format) {
      throw new \Exception('Format should be instance of '.Format::class.'.');
    }

    $formatObj->setRetina($retina);

    $this->determineStatusBlurred($formatObj, $blurred, $image, $user);

    $this->determineStatusWatermarked($formatObj, $watermarked, $image, $user);


    // DURATION
    $durationToUse = $duration;
    if ($durationToUse === NULL) {
      $durationToUse = $this->config['settings']['duration'];
    }

    if (!isset($this->config['formats'][$formatObj->getFormat()])) {
      throw new \Exception('Format "'.$formatObj->getFormat().'" not found.');
    }
    $formatConfigToUse = $this->config['formats'][$formatObj->getFormat()];

    // TIME AND DURATION
    $timeAndDuration = $this->getTimeAndDuration($durationToUse);

    // FILE
    $file = $this->sanitizingHelper->ensureSep($image->getFile(), FALSE);

    $signature = $this->getSignature(
      $file,
      $image->getId(),
      $timeAndDuration['time'],
      $timeAndDuration['duration'],
      $formatObj->getFormatAdjusted(),
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
      'format' => $formatObj->getFormatAdjusted(),
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
   * Returns an image url.
   *
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\Image $image
   * @param null $format
   * @param null $duration
   * @param null $clientId
   * @param null $clientSecret
   * @return string
   * @throws \Exception
   */
  public function getSrcSimple(Image $image, $format = NULL, $duration = NULL, $clientId = NULL, $clientSecret = NULL) {
    return $this->getSrc($image, NULL, $format, FALSE, NULL, NULL, $duration, $clientId, $clientSecret);
  }

  /**
   * Get default format.
   *
   * @return string
   */
  public function getFormatDefault() {
    if ($this->formatDefault === NULL) {
      $this->formatDefault = 'thumb';

      foreach ($this->config['formats'] as $formatKey => $formatConfig) {
        if ($formatConfig['default']) {
          $this->formatDefault = $formatKey;
          break;
        }
      }
    }

    return $this->formatDefault;
  }

  public function getFormatsBlurred() {
    if ($this->formatsBlurred === NULL) {
      $this->formatsBlurred = [];

      foreach ($this->config['formats'] as $formatKey => $formatConfig) {
        if ($formatConfig['blurred']) {
          $this->formatsBlurred[] = $formatKey;
        }
      }
    }

    return $this->formatsBlurred;
  }

  public function getFormatsWatermarked() {
    if ($this->formatsWatermarked === NULL) {
      $this->formatsWatermarked = [];

      foreach ($this->config['formats'] as $formatKey => $formatConfig) {
        if ($formatConfig['watermarked']) {
          $this->formatsWatermarked[] = $formatKey;
        }
      }
    }

    return $this->formatsWatermarked;
  }

  public function determineStatusBlurred(Format $formatObj, $blurred, Image $image, User $user = NULL) {
    if ($blurred === TRUE) {
      $formatObj->setBlurred(TRUE);
    } elseif ($blurred === NULL) {
      if (in_array($formatObj->getFormat(), $this->getFormatsBlurred())) {
        $formatObj->setBlurred($image->useBlurredFormat($user));
      }
    }
  }

  public function determineStatusWatermarked(Format $formatObj, $watermarked, Image $image, User $user = NULL) {
    if ($watermarked === TRUE) {
      $formatObj->setWatermarked(TRUE);
    } elseif ($watermarked === NULL) {
      if (in_array($formatObj->getFormat(), $this->getFormatsWatermarked())) {
        $formatObj->setWatermarked($image->useWatermarkedFormat($user));
      }
    }
  }

  public function createFormatObj($format, Image $image, User $user = NULL, $retina = FALSE, $blurred = NULL, $watermarked = NULL) {
    $formatObj = new Format($format, $this->config['suffixes']);
    $formatObj->setRetina($retina);

    $this->determineStatusBlurred($formatObj, $blurred, $image, $user);
    $this->determineStatusWatermarked($formatObj, $watermarked, $image, $user);

    return $formatObj;
  }

  /**
   * @param $formatAdjusted
   * @return Format
   */
  public function createFormatObjFromString($formatAdjusted) {
    $format = $formatAdjusted;
    $retina = FALSE;
    $blurred = FALSE;
    $watermarked = FALSE;


    $suffix = $this->config['suffixes']['retina']['format'];
    $suffixLength = strlen($suffix);
    if (substr($format, -$suffixLength) === $suffix) {
      $retina = TRUE;
      $format = substr($format, 0, -$suffixLength);
    }

    $suffix = $this->config['suffixes']['blurred']['format'];
    $suffixLength = strlen($suffix);
    if (substr($format, -$suffixLength) === $suffix) {
      $blurred = TRUE;
      $format = substr($format, 0, -$suffixLength);
    }

    $suffix = $this->config['suffixes']['watermarked']['format'];
    $suffixLength = strlen($suffix);
    if (substr($format, -$suffixLength) === $suffix) {
      $watermarked = TRUE;
      $format = substr($format, 0, -$suffixLength);
    }

    /** @var Format $formatObj */
    $formatObj = new Format($format, $this->config['suffixes']);
    $formatObj->setRetina($retina);
    $formatObj->setBlurred($blurred);
    $formatObj->setWatermarked($watermarked);

    return $formatObj;
  }

  public function getFormatSettings(Format $formatObj, Image $image = NULL) {
    $arguments = $this->getFormatArguments($formatObj);

    $settings = $this->config['formats'][$arguments['format']];
    if ($image) {
      if ($image->hasClipping($arguments['format'])) {
        $settings['clip'] = $image->getClipping($arguments['format']);
      } elseif ($image->hasFocalPoint()) {
        $settings['focal'] = $image->getFocalPoint();
      }
    }

    $settings['retina']   = $arguments['--retina'];
    $settings['blur']     = $arguments['--blur'];
    $settings['overlay']  = $arguments['--overlay'];
    $settings['oGravity'] = $arguments['--oGravity'];
    $settings['oScale']   = $arguments['--oScale'];

    return $settings;
  }

  public function getFormatArguments(Format $formatObj) {
    $retina = (int) $formatObj->isRetina();
    $blurred = 0;
    $overlay = FALSE;
    $oGravity = FALSE;
    $oScale = FALSE;
    $format = $formatObj->getFormat();

    if ($formatObj->isBlurred()) {
      $blurred  = $this->config['overlays']['blurred']['blur'];
      $overlay  = $this->config['overlays']['blurred']['file'];
      $oGravity = $this->config['overlays']['blurred']['gravity'];
      $oScale   = $this->config['overlays']['blurred']['scale'];
    } elseif ($formatObj->isWatermarked()) {
      $blurred  = $this->config['overlays']['watermarked']['blur'];
      $overlay  = $this->config['overlays']['watermarked']['file'];
      $oGravity = $this->config['overlays']['watermarked']['gravity'];
      $oScale   = $this->config['overlays']['watermarked']['scale'];
    }

    return [
      'format'       => $format,
      '--retina'     => $retina,
      '--blur'       => $blurred,
      '--overlay'    => $overlay,
      '--oGravity'   => $oGravity,
      '--oScale'     => $oScale
    ];
  }

  /**
   * Assemble string to sign for an image.
   *
   * @param $file
   * @param $id
   * @param $time
   * @param $duration
   * @param $formatAdjusted
   * @param $clientId
   * @param $clientSecret
   * @return string
   */
  public function getSignature($file, $id, $time, $duration, $formatAdjusted, $clientId, $clientSecret) {
    $stringToSign = $file."\n";
    $stringToSign .= $id."\n";
    $stringToSign .= $time."\n";
    $stringToSign .= $duration."\n";
    $stringToSign .= $formatAdjusted."\n";
    $stringToSign .= $clientId."\n";

    return $this->hmacHelper->sign($stringToSign, $clientSecret);
  }

  public function getFileCaches($file, $format = NULL) {
    $retinaValues = [TRUE, FALSE];
    $blurredValues = [TRUE, FALSE];
    $watermarkedValues = [TRUE, FALSE];

    $fileCaches = [];
    foreach ($this->config['formats'] as $formatKey => $formatConfig) {
      if (($format === NULL) || ($format === $formatKey)) {
        foreach ($retinaValues as $retinaValue) {
          foreach ($blurredValues as $blurredValue) {
            foreach ($watermarkedValues as $watermarkedValue) {
              $formatObj = new Format($formatKey, $this->config['suffixes']);
              $formatObj->setRetina($retinaValue);
              $formatObj->setBlurred($blurredValue);
              $formatObj->setWatermarked($watermarkedValue);

              $fileCaches[] = $this->getFileCache($file, $formatObj);
            }
          }
        }
      }
    }

    return array_unique($fileCaches);
  }

  public function getFileCache($file, Format $formatObj) {
    $pathinfo = pathinfo($file);

    $formatPlain = $formatObj->getFormat();
    $formatSuffix = $formatObj->getFormatSuffix();

    $formatConfig = [];
    if (isset($this->config['formats'][$formatPlain])) {
      $formatConfig = $this->config['formats'][$formatPlain];
    }

    $folderCache = '';
    if ($formatPlain.$formatSuffix !== '') {
      $folderCache .= $this->sanitizingHelper->normalizeFolderRelative($formatPlain.$formatSuffix);
    }
    if ($pathinfo['dirname'] !== '.') {
      $folderCache .= $this->sanitizingHelper->normalizeFolderRelative($pathinfo['dirname']);
    }

    // Use png as extension if needed.
    $fileCache = $pathinfo['filename'].'.jpg';
    if (isset($formatConfig['type'])) {
      if ($formatConfig['type'] === 'png') {
        $fileCache = $pathinfo['filename'].'.png';
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
   * @param \Symfony\Component\HttpKernel\Kernel $kernel
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function dispatch($format, $id, $file, Kernel $kernel, Request $request = NULL) {
    if ($request === NULL) {
      $request = Request::createFromGlobals();
    }

    /**************************************************************************/
    /* VARIABLES                                                              */
    /**************************************************************************/

    $folders = $this->config['folders'];
    $formats = $this->config['formats'];
    $fallbacks = $this->config['fallbacks'];
    $clients = $this->config['clients'];

    $query = $request->query->all();

    ini_set('memory_limit', $this->config['settings']['memory_limit']);

    $dirOrig = $this->sanitizingHelper->normalizeFolderAbsolute($folders['orig']);
    $dirCache = $this->sanitizingHelper->normalizeFolderAbsolute($folders['cache']);

    $formatObj = $this->createFormatObjFromString($format);
    $formatArguments = $this->getFormatArguments($formatObj);

    /**************************************************************************/
    /* CHECK PARAMS                                                           */
    /**************************************************************************/

    // ROUTE PARAMS
    $invalidRequest = FALSE;
    if (empty($format)) {
      $formatObj = $this->createFormatObjFromString($this->getFormatDefault());

      if ($this->debug) {
        $this->logger->error('Format is null.');
      }
      $invalidRequest = TRUE;
    }
    if (empty($format)) {
      if ($this->debug) {
        $this->logger->error('ID is null.');
      }
      $invalidRequest = TRUE;
    }
    if (empty($format)) {
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

    if (!isset($formats[$formatObj->getFormat()])) {
      if ($this->debug) {
        $this->logger->error('Format is invalid.');
      }
      $invalidRequest = TRUE;
    }

    if ($invalidRequest) {
      return $this->generateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $fallbacks['412'],
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['412']), $this->createFormatObjFromString($format)),
      ], $formatArguments), 412, $kernel, $request);
    }


    /******************************************************************************/
    /* CHECK ACCESS                                                               */
    /******************************************************************************/

    $access = TRUE;
    if ($formats[$formatObj->getFormat()]['restricted']) {
      if (!isset($clients[$query['client']])) {
        if ($this->debug) {
          $this->logger->error('Client is missing.');
        }
        $access = FALSE;
      } elseif ($query['sig'] !== $this->getSignature($file, $id, $query['ts'], $query['sec'], $format, $query['client'], $clients[$query['client']]['secret'])) {
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
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['403']), $formatObj),
      ], $formatArguments), 403, $kernel, $request);
    }

    /******************************************************************************/
    /* CHECK FOR FILE                                                             */
    /******************************************************************************/

    $fileOrig = $dirOrig.$file;
    $fileCache = $dirCache.$this->getFileCache($file, $formatObj);

    if (file_exists($fileOrig)) {
      return $this->generateAndServe(array_merge([
        'image'      => $id,
        'path-orig'  => $fileOrig,
        'path-cache' => $fileCache,
      ], $formatArguments), 200, $kernel, $request);
    } else {
      if ($this->debug) {
        $this->logger->error('Orig file "'.$fileOrig.'" can not be found.');
      }
      return $this->generateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $fallbacks['404'],
        'path-cache' => $dirCache.$this->getFileCache(basename($fallbacks['404']), $formatObj),
      ], $formatArguments), 404, $kernel, $request);
    }
  }

  /**
   * Serves (and generats) a specific format for an image.
   *
   * @param $arguments
   * @param integer $statusCode
   * @param \Symfony\Component\HttpKernel\Kernel $kernel
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function generateAndServe($arguments, $statusCode, Kernel $kernel, Request $request = NULL) {
    $file = $arguments['path-cache'];

    if (!file_exists($file)) {
      $command = ['command' => GenerateCommand::name];
      $arguments = array_merge($command, $arguments);

      $application = new Application($kernel);
      $application->add(new GenerateCommand());
      $application->setAutoExit(FALSE);

      $input = new ArrayInput($arguments);
      $output = new BufferedOutput();

      $command = $application->find('hbm:image-delivery:generate');
      $command->run($input, $output);
    }

    return $this->serve($file, $statusCode, $request);
  }

}
