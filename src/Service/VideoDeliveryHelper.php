<?php

namespace HBM\MediaDeliveryBundle\Service;

use HBM\MediaDeliveryBundle\Entity\Interfaces\Video;
use Symfony\Component\HttpFoundation\Request;

/**
 * Makes video delivery easy.
 */
class VideoDeliveryHelper extends AbstractDeliveryHelper
{
    /**
     * Returns an image url.
     *
     * @param null $duration
     * @param null $clientId
     * @param null $clientSecret
     *
     * @throws \Exception
     */
    public function getSrc(Video $video, $file, $duration = null, $clientId = null, $clientSecret = null): string
    {
        // CLIENT ID
        $clientIdToUse = $clientId;

        if ($clientIdToUse === null) {
            foreach ($this->config['clients'] as $clientKey => $clientConfig) {
                if ($clientConfig['default']) {
                    $clientIdToUse = $clientKey;
                }
            }
        }

        // CLIENT SECRET
        $clientSecretToUse = $clientSecret;

        if ($clientSecretToUse === null) {
            if (!isset($this->config['clients'][$clientIdToUse])) {
                throw new \OutOfBoundsException('Client "' . $clientIdToUse . '" not found.');
            }

            $clientSecretToUse = $this->config['clients'][$clientIdToUse]['secret'];
        }

        // DURATION
        $durationToUse = $duration;

        if ($durationToUse === null) {
            $durationToUse = $this->config['settings']['duration'];
        }

        // TIME AND DURATION
        $timeAndDuration = $this->getTimeAndDuration($durationToUse);

        // FILE
        $file = $this->sanitizingHelper->ensureSep($file, false);

        $signature = $this->getSignature(
            $file,
            $video->getId(),
            $timeAndDuration['time'],
            $timeAndDuration['duration'],
            $clientIdToUse,
            $clientSecretToUse
        );

        $paramsQuery = [
          'sig'    => $signature,
          'ts'     => $timeAndDuration['time'],
          'sec'    => $timeAndDuration['duration'],
          'client' => $clientIdToUse,
        ];

        $paramsRoute = [
          'id'   => $video->getId(),
          'file' => $file,
        ];

        return $this->router->generate('hbm_video_delivery_src', $paramsRoute) . '?' . http_build_query($paramsQuery);
    }

    /**
     * Assemble string to sign for an image.
     */
    public function getSignature($file, $id, $time, $duration, $clientId, $clientSecret): string
    {
        $stringToSign = $file . "\n";
        $stringToSign .= $id . "\n";
        $stringToSign .= $time . "\n";
        $stringToSign .= $duration . "\n";
        $stringToSign .= $clientId . "\n";

        return $this->hmacHelper->sign($stringToSign, $clientSecret);
    }

    /**
     * Dispatches a specific format for an image.
     *
     * @param int|string $id
     * @param string     $file
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function dispatch($id, $file, Request $request = null)
    {
        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        /* VARIABLES */

        $folders   = $this->config['folders'];
        $clients   = $this->config['clients'];
        $fallbacks = $this->config['fallbacks'];

        $query = $request->query->all();

        ini_set('memory_limit', $this->config['settings']['memory_limit']);

        $dirOrig = $this->sanitizingHelper->normalizeFolderAbsolute($folders['orig']);

        /* CHECK PARAMS */

        // ROUTE PARAMS
        $invalidRequest = false;

        if ($id === null) {
            if ($this->debug) {
                $this->logger->error('ID is null.');
            }
            $invalidRequest = true;
        }

        if ($file === null) {
            if ($this->debug) {
                $this->logger->error('File is null.');
            }
            $invalidRequest = true;
        }

        // QUERY PARAMS
        $keys = ['ts', 'sec', 'client', 'sig'];
        foreach ($keys as $key) {
            if (!isset($query[$key])) {
                if ($this->debug) {
                    $this->logger->error('Query param "' . $key . '" is missing.');
                }
                $invalidRequest = true;
            }
        }

        if ($invalidRequest) {
            return $this->serve($fallbacks['412'], 412, $request);
        }

        /* CHECK ACCESS */

        $access = true;

        if (!isset($clients[$query['client']])) {
            if ($this->debug) {
                $this->logger->error('Client is missing.');
            }
            $access = false;
        } elseif ($query['sig'] !== $this->getSignature($file, $id, $query['ts'], $query['sec'], $query['client'], $clients[$query['client']]['secret'])) {
            if ($this->debug) {
                $this->logger->error('Signature is invalid.');
            }
            $access = false;
        } elseif (($query['ts'] + $query['sec']) < time()) {
            if ($this->debug) {
                $this->logger->error('Timestamp is too old.');
            }
            $access = false;
        }

        if (!$access) {
            return $this->serve($fallbacks['403'], 403, $request);
        }

        /* CHECK FOR FILE */

        $fileOrig = $dirOrig . $file;

        if (file_exists($fileOrig)) {
            return $this->serve($fileOrig, 200, $request);
        }

        if ($this->debug) {
            $this->logger->error('Orig file "' . $fileOrig . '" can not be found.');
        }

        return $this->serve($fallbacks['404'], 404, $request);
    }
}
