<?php

namespace HBM\ImageDeliveryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{

  public function serveAction()
  {
    ini_set('memory_limit', '512M');

    /******************************************************************************/
    /* GENERATE AND SERVE                                                         */
    /******************************************************************************/

    if (!function_exists('pbyOutputOrGenerate')) {
      function pbyGenerateAndServe($arguments) {
        $file = $arguments['path-cache'];

        /**********************************************************************/
        /* GENERATE                                                           */
        /**********************************************************************/
        if (!file_exists($file)) {
          $arguments['command'] = 'pbp:images:generate-format';

          /******************************************************************/

          $kernel = new AppKernel($_SERVER['SYMFONY_PP_ENV'], false);

          $input  = new ArgvInput(array());

          $application = new Application($kernel);
          $application->add(new ImagesGenerateFormatCommand());
          $application->setAutoExit(FALSE);
          $application->run($input);

          /******************************************************************/

          $input  = new ArrayInput($arguments);
          $output = new ConsoleOutput();

          $command = $application->find('pbp:images:generate-format');
          $command->run($input, $output);
        }


        /**********************************************************************/
        /* SERVE                                                              */
        /**********************************************************************/
        $fileModificationTime = filemtime($file);

        $headers = array();
        foreach ($_SERVER as $name => $value) {
          if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
          }
        }

        if (isset($headers['If-Modified-Since'])) {
          $ifModifiedSinceHeader = strtotime($headers['If-Modified-Since']);

          if ($ifModifiedSinceHeader > $fileModificationTime) {
            header('HTTP/1.1 304 Not Modified');
            exit;
          }
        }

        $cache_sec = 86400; // 24h
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($finfo, $file);

        header('Pragma: public');
        header('Cache-Control: max-age='.$cache_sec);
        header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + $cache_sec));
        header("Last-Modified: ".gmdate("D, d M Y H:i:s", $fileModificationTime)." GMT");

        $images_dir = MediaImage::folderCache($_SERVER['SYMFONY__PP__DIR__DATA']);

        if (isset($_SERVER['SYMFONY_PP_SERVER']) && ($_SERVER['SYMFONY_PP_SERVER'] == 'apache') && isset($_SERVER['SYMFONY_PP_SERVE_IMAGES'])) {

          /******************************************************************/
          /* SERVE THROUGH X-Sendfile                                       */
          /******************************************************************/

          $images_prefix = '/'.rtrim(ltrim($_SERVER['SYMFONY_PP_SERVE_IMAGES'], '/'), '/').'/';
          $file = str_replace($images_dir, $images_prefix, $file);

          header('X-Sendfile: '.$file);
          header('Content-Type: '.$contentType);
          header('Content-Disposition: inline; filename="'.basename($file).'"');
          exit;

        } elseif (isset($_SERVER['SYMFONY_PP_SERVER']) && ($_SERVER['SYMFONY_PP_SERVER'] == 'nginx') && isset($_SERVER['SYMFONY_PP_SERVE_IMAGES'])) {

          /******************************************************************/
          /* SERVE THROUGH X-Accel-Redirect                                 */
          /******************************************************************/

          $images_prefix = '/'.rtrim(ltrim($_SERVER['SYMFONY_PP_SERVE_IMAGES'], '/'), '/').'/';
          $file = str_replace($images_dir, $images_prefix, $file);

          header("X-Accel-Redirect: $file");
          header('Content-Type: '.$contentType);
          header('Content-Disposition: inline; filename="'.basename($file).'"');
          exit;

        } else {

          /******************************************************************/
          /* SERVE THROUGH PHP                                              */
          /******************************************************************/

          header('Content-Type: '.$contentType);
          header('Content-Length: '.filesize($file));
          header('Content-Disposition: inline; filename="'.basename($file).'"');
          header('Accept-Ranges: bytes');

          $chunksize = 1024*1024;

          $handle = fopen($file, "rb");
          while (!feof($handle)) {
            echo fread($handle, $chunksize);
            ob_flush();
            flush();
          }
          exit;

        }
      }
    }


    /******************************************************************************/
    /* FOLDER                                                                     */
    /******************************************************************************/

    // Replace windows folder delimiter
    $dir_data   = $_SERVER['SYMFONY__PP__DIR__DATA'];
    $dir_data = str_replace('\\', '/', $dir_data);

    $folder_orig  = rtrim($dir_data, '/').'/images/';
    $folder_cache = rtrim($dir_data, '/').'/images_cache/';
    $folder_dummy = __DIR__.'/';


    /******************************************************************************/
    /* PARAMS                                                                     */
    /******************************************************************************/

    $format = 'thumb';
    if (isset($_GET['image-format'])) {
      $format = $_GET['image-format'];
    }

    $parameters = ImagesGenerateFormatCommand::determineParameters($format, $folder_dummy);

    $format_category = $parameters['args']['format'];
    $format_suffix = $parameters['suffix'];

    $formats = [
      'listing-overview'   => ['type' => 'jpg'],
      'listing-gallery'    => ['type' => 'jpg'],
      'listing-video'      => ['type' => 'jpg'],
      'listing-cover'      => ['type' => 'jpg'],
      'slider'             => ['type' => 'jpg'],
      'video'              => ['type' => 'jpg'],
      'orig'               => ['type' => 'png'],
      'full'               => ['type' => 'jpg'],
      'gallery'            => ['type' => 'jpg'],
      'thumb'              => ['type' => 'jpg'],
      'thumb-square-trans' => ['type' => 'png'],
    ];


    /******************************************************************************/
    /* FILES                                                                      */
    /******************************************************************************/

    $file_403 = $folder_dummy.'bunny403.png';
    $file_404 = $folder_dummy.'bunny404.png';
    $file_412 = $folder_dummy.'bunny412.png';

    $file_403_format = $folder_cache.'bunny403_'.$format_category.$format_suffix.'.jpg';
    $file_404_format = $folder_cache.'bunny404_'.$format_category.$format_suffix.'.jpg';
    $file_412_format = $folder_cache.'bunny412_'.$format_category.$format_suffix.'.jpg';


    /******************************************************************************/
    /* VARIABLES                                                                  */
    /******************************************************************************/
    $keys = ['ts', 'sec', 'client', 'sig', 'custom', 'image-path', 'image-id', 'image-format'];
    foreach ($keys as $key) {
      if (!isset($_GET[$key])) {
        pbyGenerateAndServe(array_merge([
          'image'      => NULL,
          'path-orig'  => $file_412,
          'path-cache' => $file_412_format,
          '--custom'   => false
        ], $parameters['args']));
      }
    }

    if (!isset($formats[$format_category])) {
      $format_category = 'thumb';
    }

    $file_orig = $folder_orig.$_GET['image-path'];
    $file_cache = MediaImage::fileCache($_GET['image-path'], $format_category.$format_suffix, $formats[$format_category], $dir_data);

    /******************************************************************************/
    /* CHECK ACCESS                                                               */
    /******************************************************************************/

    $stringToSign = $_GET['image-path']."\n";
    $stringToSign .= $_GET['image-id']."\n";
    $stringToSign .= $_GET['ts']."\n";
    $stringToSign .= $_GET['sec']."\n";
    $stringToSign .= $format."\n";
    $stringToSign .= $_GET['custom']."\n";
    $stringToSign .= $_GET['client']."\n";

    $access = TRUE;
    if (!isset(Media::$clients[$_GET['client']])) {
      $access = FALSE;
    } elseif ($_GET['sig'] != Media::sign($stringToSign, Media::$clients[$_GET['client']])) {
      $access = FALSE;
    } elseif (($_GET['ts'] + $_GET['sec']) < time()) {
      $access = FALSE;
    }

    if (!$access) {
      pbyGenerateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $file_403,
        'path-cache' => $file_403_format,
        '--custom'   => false,
      ], $parameters['args']));
    }

    /******************************************************************************/
    /* CHECK FOR FILE                                                             */
    /******************************************************************************/

    if (file_exists($file_orig)) {
      pbyGenerateAndServe(array_merge([
        'image'      => $_GET['image-id'],
        'path-orig'  => $file_orig,
        'path-cache' => $file_cache,
        '--custom'   => $_GET['custom'],
      ], $parameters['args']));
    } else {
      pbyGenerateAndServe(array_merge([
        'image'      => NULL,
        'path-orig'  => $file_404,
        'path-cache' => $file_404_format,
        '--custom'   => false,
      ], $parameters['args']));
    }

  }

}
