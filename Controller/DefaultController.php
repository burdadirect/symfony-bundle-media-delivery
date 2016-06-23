<?php

namespace HBM\ImageDeliveryBundle\Controller;

use HBM\HelperBundle\Services\HmacHelper;
use HBM\HelperBundle\Services\SanitizingHelper;
use HBM\ImageDeliveryBundle\Command\ImageDeliveryGenerateFormatCommand;
use HBM\ImageDeliveryBundle\Services\ImageDeliveryHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{

  public function serveAction(Request $request, $format, $id, $file)
  {
    /**************************************************************************/
    /* VARIABLES                                                              */
    /**************************************************************************/

    $config = $this->container->getParameter('hbm.image_delivery');
    $folders = $config['folders'];
    $formats = $config['formats'];
    $images = $config['images'];
    $clients = $config['clients'];

    $query = $request->query->all();

    ini_set('memory_limit', $config['settings']['memory_limit']);

    $dirOrig = $this->getSanitizingHelper()->normalizeFolderRelative($folders['orig']);
    $dirCache = $this->getSanitizingHelper()->normalizeFolderRelative($folders['cache']);


    /**************************************************************************/
    /* CHECK PARAMS                                                           */
    /**************************************************************************/

    $arguments = ImageDeliveryGenerateFormatCommand::determineArguments($format, $images);

    $formatPlain = $this->getImageDeliveryHelper()->getFormatPlain($format);

    // ROUTE PARAMS
    $invalidRequest = FALSE;
    if ($format === NULL) {
      $invalidRequest = TRUE;
    }
    if ($id === NULL) {
      $invalidRequest = TRUE;
    }
    if ($file === NULL) {
      $invalidRequest = TRUE;
    }

    // QUERY PARAMS
    $keys = ['ts', 'sec', 'client', 'sig', 'custom'];
    foreach ($keys as $key) {
      if (!isset($query[$key])) {
        $invalidRequest = TRUE;
      }
    }

    if (!isset($formats[$formatPlain])) {
      $invalidRequest = TRUE;
    }

    if ($invalidRequest) {
      return $this->generateAndServe($request, array_merge([
        'image'      => NULL,
        'path-orig'  => $images['412'],
        'path-cache' => $dirCache.$this->getImageDeliveryHelper()->getFileCache(basename($images['412']), $format),
        '--custom'   => false
      ], $arguments));
    }


    /******************************************************************************/
    /* CHECK ACCESS                                                               */
    /******************************************************************************/

    $stringToSign = $file."\n";
    $stringToSign .= $id."\n";
    $stringToSign .= $query['ts']."\n";
    $stringToSign .= $query['sec']."\n";
    $stringToSign .= $format."\n";
    $stringToSign .= $query['custom']."\n";
    $stringToSign .= $query['client']."\n";

    $access = TRUE;
    if (!isset($clients[$query['client']])) {
      $access = FALSE;
    } elseif ($query['sig'] !== $this->getHmacHelper()->sign($stringToSign, $clients[$query['client']]['secret'])) {
      $access = FALSE;
    } elseif (($query['ts'] + $query['sec']) < time()) {
      $access = FALSE;
    }

    if (!$access) {
      return $this->generateAndServe($request, array_merge([
        'image'      => NULL,
        'path-orig'  => $images['403'],
        'path-cache' => $dirCache.$this->getImageDeliveryHelper()->getFileCache(basename($images['403']), $format),
        '--custom'   => false,
      ], $arguments));
    }

    /******************************************************************************/
    /* CHECK FOR FILE                                                             */
    /******************************************************************************/

    $fileOrig = $dirOrig.$file;
    $fileCache = $dirCache.$this->getImageDeliveryHelper()->getFileCache($file, $format);

    if (file_exists($fileOrig)) {
      return $this->generateAndServe($request, array_merge([
        'image'      => $id,
        'path-orig'  => $fileOrig,
        'path-cache' => $fileCache,
        '--custom'   => $query['custom'],
      ], $arguments));
    } else {
      return $this->generateAndServe($request, array_merge([
        'image'      => NULL,
        'path-orig'  => $images['404'],
        'path-cache' => $dirCache.$this->getImageDeliveryHelper()->getFileCache(basename($images['404']), $format),
        '--custom'   => false,
      ], $arguments));
    }

  }

  private function generateAndServe(Request $request, $arguments) {
    $config = $this->container->getParameter('hbm.image_delivery');

    $file = $arguments['path-cache'];

    /**************************************************************************/
    /* GENERATE                                                               */
    /**************************************************************************/

    if (!file_exists($file)) {
      $arguments['command'] = 'hbm:image-delivery:generate-format';

      $kernel = $this->get('kernel');

      $application = new Application($kernel);
      $application->setAutoExit(FALSE);

      $input  = new ArrayInput($arguments);
      $output = new BufferedOutput();

      $application->run($input, $output);

      return new Response($output->fetch());
    }

    /**************************************************************************/
    /* HEADER                                                                 */
    /**************************************************************************/

    $cacheSec = $config['settings']['cache'];
    $fileModificationTime = filemtime($file);

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
      'Content-Type' => 'application/octet-stream',
      'Content-Disposition' => 'inline; filename="'.basename($file).'"',
    ];

    /**************************************************************************/
    /* SERVE                                                                  */
    /**************************************************************************/

    if (isset($config['x-sendfile'])) {
      $prefix = $this->getSanitizingHelper()->ensureSep($config['x-sendfile']['prefix'], TRUE, TRUE);
      $pathServed = str_replace($config['x-sendfile']['path'], $prefix, $file);

      $headers['X-Sendfile'] = $pathServed;
      return new Response('', 200, $headers);
    }

    if (isset($config['x-accel-redirect'])) {
      $prefix = $this->getSanitizingHelper()->ensureSep($config['x-accel-redirect']['prefix'], TRUE, TRUE);
      $pathServed = str_replace($config['x-accel-redirect']['path'], $prefix, $file);

      $headers['X-Accel-Redirect'] = $pathServed;
      return new Response('', 200, $headers);
    }

    $headers['Content-Type'] = mime_content_type($file);
    $headers['Content-Length'] = filesize($file);

    return new BinaryFileResponse($file, 200, $headers);

  }

  /**
   * @return ImageDeliveryHelper
   */
  private function getImageDeliveryHelper() {
    return $this->get('hbm.helper.image_delivery');
  }

  /**
   * @return SanitizingHelper
   */
  private function getSanitizingHelper() {
    return $this->get('hbm.helper.sanitizing');
  }

  /**
   * @return HmacHelper
   */
  private function getHmacHelper() {
    return $this->get('hbm.helper.hmac');
  }

}
