<?php

namespace HBM\MediaDeliveryBundle\Service;

use HBM\HelperBundle\Service\HmacHelper;
use HBM\HelperBundle\Service\SanitizingHelper;
use HBM\MediaDeliveryBundle\Command\GenerateCommand;
use HBM\MediaDeliveryBundle\Entity\Format;
use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Entity\Interfaces\User;
use HBM\MediaDeliveryBundle\HttpFoundation\CustomBinaryFileResponse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Makes image delivery easy.
 */
class ImageDeliveryHelper extends AbstractDeliveryHelper
{
    private ?string $formatDefault = null;

    private ?array $formatsBlurred = null;

    private ?array $formatsWatermarked = null;

    private KernelInterface $kernel;

    private GenerateCommand $generateCommand;

    public function __construct(array $config, KernelInterface $kernel, GenerateCommand $generateCommand, SanitizingHelper $sanitizingHelper, HmacHelper $hmacHelper, RouterInterface $router, LoggerInterface $logger)
    {
        parent::__construct($config, $sanitizingHelper, $hmacHelper, $router, $logger);

        $this->kernel          = $kernel;
        $this->generateCommand = $generateCommand;
    }

    /**
     * Returns an image url.
     */
    public function getSrc(Image $image, ?User $user = null, ?string $format = null, ?bool $retina = false, ?bool $blurred = null, ?bool $watermarked = null, int|string|null $duration = null, int|string|null $clientId = null, ?string $clientSecret = null): string
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

        // FORMAT
        $formatToUse = $format;

        if ($formatToUse === null) {
            $formatToUse = $this->getFormatDefault();
        }

        // FORMAT OBJECT
        $formatObj = new Format($formatToUse, $this->config['suffixes']);

        if (!$formatObj instanceof Format) {
            throw new \InvalidArgumentException('Format should be instance of ' . Format::class . '.');
        }

        $formatObj->setRetina($retina);

        $this->determineStatusBlurred($formatObj, $blurred, $image, $user);

        $this->determineStatusWatermarked($formatObj, $watermarked, $image, $user);

        // DURATION
        $durationToUse = $duration;

        if ($durationToUse === null) {
            $durationToUse = $this->config['settings']['duration'];
        }

        if (!isset($this->config['formats'][$formatObj->getFormat()])) {
            throw new \OutOfBoundsException('Format "' . $formatObj->getFormat() . '" not found.');
        }
        $formatConfigToUse = $this->config['formats'][$formatObj->getFormat()];

        // TIME AND DURATION
        $timeAndDuration = $this->getTimeAndDuration($durationToUse);

        // FILE
        $file = $this->sanitizingHelper->ensureSep($image->getFile(), false);

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
            'sig'    => $signature,
            'ts'     => $timeAndDuration['time'],
            'sec'    => $timeAndDuration['duration'],
            'client' => $clientIdToUse,
        ];

        $paramsRoute = [
            'format' => $formatObj->getFormatAdjusted(),
            'id'     => $image->getId(),
            'file'   => $file,
        ];

        $url = $this->router->generate('hbm_image_delivery_src', $paramsRoute);

        if ($formatConfigToUse['restricted']) {
            $url .= '?' . http_build_query($paramsQuery);
        }

        return $url;
    }

    /**
     * Returns an image url.
     *
     * @throws \Exception
     */
    public function getSrcSimple(Image $image, ?string $format = null, int|string|null $duration = null, int|string|null $clientId = null, ?string $clientSecret = null): string
    {
        return $this->getSrc($image, null, $format, false, null, null, $duration, $clientId, $clientSecret);
    }

    /**
     * Get default format.
     */
    public function getFormatDefault(): string
    {
        if ($this->formatDefault === null) {
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

    public function getFormatsBlurred(): array
    {
        if ($this->formatsBlurred === null) {
            $this->formatsBlurred = [];

            foreach ($this->config['formats'] as $formatKey => $formatConfig) {
                if ($formatConfig['blurred']) {
                    $this->formatsBlurred[] = $formatKey;
                }
            }
        }

        return $this->formatsBlurred;
    }

    public function getFormatsWatermarked(): array
    {
        if ($this->formatsWatermarked === null) {
            $this->formatsWatermarked = [];

            foreach ($this->config['formats'] as $formatKey => $formatConfig) {
                if ($formatConfig['watermarked']) {
                    $this->formatsWatermarked[] = $formatKey;
                }
            }
        }

        return $this->formatsWatermarked;
    }

    public function determineStatusBlurred(Format $formatObj, ?bool $blurred, Image $image, ?User $user = null): void
    {
        if ($blurred === true) {
            $formatObj->setBlurred(true);
        } elseif ($blurred === null) {
            if (\in_array($formatObj->getFormat(), $this->getFormatsBlurred(), true)) {
                $formatObj->setBlurred($image->useBlurredFormat($user));
            }
        }
    }

    public function determineStatusWatermarked(Format $formatObj, ?bool $watermarked, Image $image, ?User $user = null): void
    {
        if ($watermarked === true) {
            $formatObj->setWatermarked(true);
        } elseif ($watermarked === null) {
            if (\in_array($formatObj->getFormat(), $this->getFormatsWatermarked(), true)) {
                $formatObj->setWatermarked($image->useWatermarkedFormat($user));
            }
        }
    }

    public function createFormatObj($format, Image $image, ?User $user = null, ?bool $retina = false, ?bool $blurred = null, ?bool $watermarked = null): Format
    {
        $formatObj = new Format($format, $this->config['suffixes']);
        $formatObj->setRetina($retina);

        $this->determineStatusBlurred($formatObj, $blurred, $image, $user);
        $this->determineStatusWatermarked($formatObj, $watermarked, $image, $user);

        return $formatObj;
    }

    public function createFormatObjFromString(?string $formatAdjusted): Format
    {
        $format      = $formatAdjusted;
        $retina      = false;
        $blurred     = false;
        $watermarked = false;

        $suffix       = $this->config['suffixes']['retina']['format'];
        $suffixLength = \strlen($suffix);

        if (substr($format, -$suffixLength) === $suffix) {
            $retina = true;
            $format = substr($format, 0, -$suffixLength);
        }

        $suffix       = $this->config['suffixes']['blurred']['format'];
        $suffixLength = \strlen($suffix);

        if (substr($format, -$suffixLength) === $suffix) {
            $blurred = true;
            $format  = substr($format, 0, -$suffixLength);
        }

        $suffix       = $this->config['suffixes']['watermarked']['format'];
        $suffixLength = \strlen($suffix);

        if (substr($format, -$suffixLength) === $suffix) {
            $watermarked = true;
            $format      = substr($format, 0, -$suffixLength);
        }

        $formatObj = new Format($format, $this->config['suffixes']);
        $formatObj->setRetina($retina);
        $formatObj->setBlurred($blurred);
        $formatObj->setWatermarked($watermarked);

        return $formatObj;
    }

    public function getFormatSettings(Format $formatObj, ?Image $image = null)
    {
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

    public function getFormatArguments(Format $formatObj): array
    {
        $retina   = (int) $formatObj->isRetina();
        $blurred  = 0;
        $overlay  = false;
        $oGravity = false;
        $oScale   = false;
        $format   = $formatObj->getFormat();

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
            'format'     => $format,
            '--retina'   => $retina,
            '--blur'     => $blurred,
            '--overlay'  => $overlay,
            '--oGravity' => $oGravity,
            '--oScale'   => $oScale,
        ];
    }

    /**
     * Assemble string to sign for an image.
     */
    public function getSignature(?string $file, int|string|null $id, int|string|null $time, int|string|null $duration, ?string $formatAdjusted, int|string|null $clientId, ?string $clientSecret): string
    {
        $stringToSign = $file . "\n";
        $stringToSign .= $id . "\n";
        $stringToSign .= $time . "\n";
        $stringToSign .= $duration . "\n";
        $stringToSign .= $formatAdjusted . "\n";
        $stringToSign .= $clientId . "\n";

        return $this->hmacHelper->sign($stringToSign, $clientSecret);
    }

    public function getFileCaches(?string $file, ?string $format = null): array
    {
        $retinaValues      = [true, false];
        $blurredValues     = [true, false];
        $watermarkedValues = [true, false];

        $fileCaches = [];
        foreach ($this->config['formats'] as $formatKey => $formatConfig) {
            if (($format === null) || ($format === $formatKey)) {
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

    public function getFileCache(?string $file, Format $formatObj): string
    {
        $pathinfo = pathinfo($file);

        $formatPlain  = $formatObj->getFormat();
        $formatSuffix = $formatObj->getFormatSuffix();

        $formatConfig = [];

        if (isset($this->config['formats'][$formatPlain])) {
            $formatConfig = $this->config['formats'][$formatPlain];
        }

        $folderCache = '';

        if ($formatPlain . $formatSuffix !== '') {
            $folderCache .= $this->sanitizingHelper->normalizeFolderRelative($formatPlain . $formatSuffix);
        }

        if ($pathinfo['dirname'] !== '.') {
            $folderCache .= $this->sanitizingHelper->normalizeFolderRelative($pathinfo['dirname']);
        }

        // Use png as extension if needed.
        $fileCache = $pathinfo['filename'] . '.jpg';

        if (isset($formatConfig['type']) && ($formatConfig['type'] === 'png')) {
            $fileCache = $pathinfo['filename'] . '.png';
        }

        return $folderCache . $fileCache;
    }

    /**
     * Dispatches a specific format for an image.
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function dispatch(string $format, string $id, string $file, ?Request $request = null): CustomBinaryFileResponse|Response
    {
        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        /* VARIABLES */

        $folders   = $this->config['folders'];
        $formats   = $this->config['formats'];
        $fallbacks = $this->config['fallbacks'];
        $clients   = $this->config['clients'];

        $query = $request->query->all();

        ini_set('memory_limit', $this->config['settings']['memory_limit']);

        $dirOrig  = $this->sanitizingHelper->normalizeFolderAbsolute($folders['orig']);
        $dirCache = $this->sanitizingHelper->normalizeFolderAbsolute($folders['cache']);

        $formatObj       = $this->createFormatObjFromString($format);
        $formatArguments = $this->getFormatArguments($formatObj);

        /* CHECK PARAMS */

        // ROUTE PARAMS
        $invalidRequest = false;

        if (empty($format)) {
            $this->log('Format is null.');
            $invalidRequest = true;
        }

        if (empty($id)) {
            $this->log('ID is null.');
            $invalidRequest = true;
        }

        if (empty($file)) {
            $this->log('File is null.');
            $invalidRequest = true;
        }

        // QUERY PARAMS
        $keys = ['ts', 'sec', 'client', 'sig'];
        foreach ($keys as $key) {
            if (!isset($query[$key])) {
                $this->log('Query param "' . $key . '" is missing.');
                $invalidRequest = true;
            }
        }

        if (!isset($formats[$formatObj->getFormat()])) {
            $this->log('Format is invalid.');
            $invalidRequest = true;
        }

        if ($invalidRequest) {
            $formatObj = $this->createFormatObjFromString($this->getFormatDefault());

            return $this->generateAndServe(array_merge([
                'image'      => null,
                'path-orig'  => $fallbacks['412'],
                'path-cache' => $dirCache . $this->getFileCache(basename($fallbacks['412']), $formatObj),
            ], $formatArguments), 412, $request);
        }

        /* CHECK ACCESS */

        $access = true;

        if ($formats[$formatObj->getFormat()]['restricted']) {
            if (!isset($clients[$query['client']])) {
                $this->log('Client is missing.');
                $access = false;
            } elseif ($query['sig'] !== $this->getSignature($file, $id, $query['ts'], $query['sec'], $format, $query['client'], $clients[$query['client']]['secret'])) {
                $this->log('Signature is invalid.');
                $access = false;
            } elseif (($query['ts'] + $query['sec']) < time()) {
                $this->log('Timestamp is too old.');
                $access = false;
            }
        }

        if (!$access) {
            return $this->generateAndServe(array_merge([
                'image'      => null,
                'path-orig'  => $fallbacks['403'],
                'path-cache' => $dirCache . $this->getFileCache(basename($fallbacks['403']), $formatObj),
            ], $formatArguments), 403, $request);
        }

        /* CHECK FOR FILE */

        $fileOrig  = $dirOrig . $file;
        $fileCache = $dirCache . $this->getFileCache($file, $formatObj);

        if (file_exists($fileOrig)) {
            return $this->generateAndServe(array_merge([
                'image'      => ($id === '-') ? null : $id,
                'path-orig'  => $fileOrig,
                'path-cache' => $fileCache,
            ], $formatArguments), 200, $request);
        }

        $this->log('Orig file "' . $fileOrig . '" can not be found.');

        return $this->generateAndServe(array_merge([
            'image'      => null,
            'path-orig'  => $fallbacks['404'],
            'path-cache' => $dirCache . $this->getFileCache(basename($fallbacks['404']), $formatObj),
        ], $formatArguments), 404, $request);
    }

    /**
     * Serves (and generates) a specific format for an image.
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     * @throws \Exception
     */
    public function generateAndServe(array $arguments, int $statusCode, ?Request $request = null): CustomBinaryFileResponse|Response
    {
        $file = $arguments['path-cache'];

        if (!file_exists($file)) {
            $application = new Application($this->kernel);
            $application->add($this->generateCommand);
            $application->find(GenerateCommand::NAME);
            $application->setAutoExit(false);

            $input = new ArrayInput(array_merge(
                ['command' => GenerateCommand::NAME],
                $arguments
            ));
            $output = new BufferedOutput();

            $command = $application->find(GenerateCommand::NAME);
            $command->run($input, $output);
        }

        return $this->serve($file, $statusCode, $request);
    }

    private function log(string $message): void
    {
        if ($this->debug) {
            $this->logger->error($message);
        }
    }
}
