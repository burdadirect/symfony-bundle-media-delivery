<?php

namespace HBM\ImageDeliveryBundle\Command;

use HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Imagine\Image\Point;

class ImageDeliveryGenerateFormatCommand extends ImageDeliveryGenerateFormatAbstractCommand
{

  protected function configure() {
    $this
      ->setName('hbm:image-delivery:generate-format')
      ->setDescription('Generate a specific format for an image.')

      ->addArgument('format',     InputArgument::REQUIRED, 'The format to generate.')
      ->addArgument('image',      InputArgument::REQUIRED, 'The id of the image.')
      ->addArgument('path-orig',  InputArgument::REQUIRED, 'The path of the original image.')
      ->addArgument('path-cache', InputArgument::REQUIRED, 'The path of the cached image.')
      ->addOption('custom',   NULL, InputOption::VALUE_NONE, 'Look in the database for a custom image clipping.')
      ->addOption('retina',   NULL, InputOption::VALUE_NONE, 'Use twice the width and height for resizing.')
      ->addOption('blur',     NULL, InputOption::VALUE_OPTIONAL, 'Blur the image with this value (percent of the sqrt(width*height)).')
      ->addOption('overlay',  NULL, InputOption::VALUE_OPTIONAL, 'Overlay the given image.')
      ->addOption('oGravity', NULL, InputOption::VALUE_OPTIONAL, 'Align the overlay (1 = top left, 2 = top center, ..., 5 = center, ..., 9 = bottom right).')
      ->addOption('oScale',   NULL, InputOption::VALUE_OPTIONAL, 'Scale the overlay (inset = scale to fit, orig = original size).');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    // Enlarge resources
    $this->enlargeResources();

    file_put_contents('/Users/d429161/Development/Repositories/pby_playboyplus/var/test.txt', 'BLA');

    // Get arguments for custom clippings
    $format = $input->getArgument('format');
    $image = $input->getArgument('image');
    $custom = $input->getOption('custom');

    $config = $this->getContainer()->getParameter('hbm.image_delivery');

    /** @var ImageDeliverable $imageObj */
    $imageObj = NULL;
    if ($image) {
      /** @var \Doctrine\ORM\EntityManager $em */
      $em = $this->getContainer()->get('doctrine')->getManager();
      $repo = $em->getRepository($config['settings']['entity_name']);

      $imageObj = $repo->find($image);
    }

    // Get arguments for default clippings
    $settings = $config['formats'][$format];
    if ($custom && $imageObj) {
      $settings['clip'] = $imageObj->getClipping($format);
    }
    $settings['retina'] = $input->getOption('retina');
    $settings['blur'] = $input->getOption('blur');
    $settings['overlay'] = $input->getOption('overlay');
    $settings['oGravity'] = $input->getOption('oGravity');
    $settings['oScale'] = $input->getOption('oScale');

    $path_orig = $input->getArgument('path-orig');
    $path_cache = $input->getArgument('path-cache');

    if (!file_exists($path_orig)) {
      $output->writeln('<cc2error>File not found.</cc2error>');
      return 404;
    }

    $this->generate($path_orig, $path_cache, $settings);

    if ($settings['exif']) {
      $this->addMetadata($path_cache, $imageObj, $output);
    }

    return 0;
  }

  /**
   * Adds several metadata in exif format to image.
   *
   * @param $path
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\ImageDeliverable $image
   */
  private function addMetadata($path, ImageDeliverable $image, OutputInterface $output = NULL) {
    $exif = $this->getContainer()->getParameter('hbm.image_delivery.exif');

    $parts = [];

    /**************************************************************************/

    // IPTC
    $ns = 'IPTC:';
    $parts[] = '-'.$ns.'Credit="'.$exif['company_short'].'"';
    $parts[] = '-'.$ns.'Source="'.$exif['url'].'"';
    $parts[] = '-'.$ns.'CopyrightNotice="'.$exif['notice'].'"';
    $parts[] = '-'.$ns.'Contact="'.$exif['contact'].'"';

    /**************************************************************************/

    // XMP (pur)
    $ns = 'XMP-pur:';
    $parts[] = '-'.$ns.'Agreement="'.$exif['notice'].'"';
    $parts[] = '-'.$ns.'Copyright="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'CreditLine="'.$exif['product'].'"';
    $parts[] = '-'.$ns.'Permissions="'.$exif['notice'].'"';
    $parts[] = '-'.$ns.'ReuseProhibited="1"';

    /**************************************************************************/

    // XMP (prism)
    // $ns = 'XMP-prism:';
    // Not suported in version 9.46
    // $parts[] = '-'.$ns.'CopyrightYear="'.date('Y').'"';

    /**************************************************************************/

    // XMP (dc)
    $ns = 'XMP-dc:';
    //$parts[] = escapeshellarg('-'.$ns.'Title="'.$title.'"');
    //$parts[] = escapeshellarg('-'.$ns.'Description="'.$description.'"');

    $parts[] = '-'.$ns.'Creator="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'Publisher="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'Rights="'.$exif['notice'].'"';

    /**************************************************************************/

    // XMP (xmpRights)
    $ns = 'XMP-xmpRights:';
    $parts[] = '-'.$ns.'Owner="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'UsageTerms="'.$exif['notice'].'"';
    $parts[] = '-'.$ns.'WebStatement="'.$exif['url'].'"';

    /**************************************************************************/

    // XMP-Ext
    $ns = 'XMP-iptcExt:';
    $parts[] = '-'.$ns.'ArtworkSource="'.$exif['product'].'"';
    $parts[] = '-'.$ns.'ArtworkSourceInventoryNo="'.$image->getId().'"';
    // Not suported in version 9.46
    //$parts[] = '-'.$ns.'ArtworkSourceInvURL="'.$url.'"';
    $parts[] = '-'.$ns.'ArtworkCopyrightNotice="'.$exif['notice'].'"';

    $parts[] = '-'.$ns.'MaxAvailHeight="'.$image->getHeight().'"';
    $parts[] = '-'.$ns.'MaxAvailWidth="'.$image->getWidth().'"';

    /**************************************************************************/

    // XMP (plus)
    $ns = 'XMP-plus:';
    if ($image->getFsk() >= 18) {
      $parts[] = '-'.$ns.'AdultContentWarning="Adult Content Warning Required"';
    } elseif ($image->getFsk() >= 16) {
      $parts[] = '-'.$ns.'AdultContentWarning="Not Required"';
    } else {
      $parts[] = '-'.$ns.'AdultContentWarning="Unknown"';
    }

    $parts[] = '-'.$ns.'CopyrightOwnerName="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'CopyrightStatus="Protected"';
    $parts[] = '-'.$ns.'CreditLineRequired="Credit Adjacent To Image"';

    $parts[] = '-'.$ns.'ImageCreatorName="'.$image->getCredit().'"';
    $parts[] = '-'.$ns.'ImageType="Photographic Image"';

    $parts[] = '-'.$ns.'LicensorName="'.$exif['company'].'"';
    $parts[] = '-'.$ns.'LicensorCity="'.$exif['city'].'"';
    $parts[] = '-'.$ns.'LicensorCountry="'.$exif['country'].'"';
    $parts[] = '-'.$ns.'LicensorStreetAddress="'.$exif['street'].'"';
    $parts[] = '-'.$ns.'LicensorPostalCode="'.$exif['zip'].'"';
    $parts[] = '-'.$ns.'LicensorRegion="'.$exif['region'].'"';
    $parts[] = '-'.$ns.'LicensorEmail="'.$exif['email'].'"';
    $parts[] = '-'.$ns.'LicensorTelephone1="'.$exif['telephone'].'"';
    $parts[] = '-'.$ns.'LicensorTelephoneType1="work"';
    $parts[] = '-'.$ns.'LicensorURL="'.$exif['url'].'"';

    /**************************************************************************/

    // XMP-Ext (iptcCore)
    $ns = 'XMP-iptcCore:';
    $parts[] = '-'.$ns.'CountryCode="'.$exif['country_code'].'"';
    $parts[] = '-'.$ns.'CreatorCity="'.$exif['city'].'"';
    $parts[] = '-'.$ns.'CreatorCountry="'.$exif['country'].'"';
    $parts[] = '-'.$ns.'CreatorAddress="'.$exif['street'].'"';
    $parts[] = '-'.$ns.'CreatorPostalCode="'.$exif['zip'].'"';
    $parts[] = '-'.$ns.'CreatorRegion="'.$exif['region'].'"';
    $parts[] = '-'.$ns.'CreatorWorkEmail="'.$exif['email'].'"';
    $parts[] = '-'.$ns.'CreatorWorkTelephone="'.$exif['telephone'].'"';
    $parts[] = '-'.$ns.'CreatorWorkURL="'.$exif['url'].'"';

    /**************************************************************************/

    $command = 'exiftool -overwrite_original '.implode(' ', $parts).' '.escapeshellarg($path);
    if ($output) {
      //$output->writeln('<cc2note>'.$command.'</cc2note>');
    }
    exec($command);
  }

  public static function determineArguments($format, $dummyImages) {
    $retina = 0;
    $blurred = 0;
    $overlay = FALSE;
    $oGravity = FALSE;
    $oScale = FALSE;
    $format_category = $format;

    if (substr($format, -8) === '-blurred') {
      $blurred = 5;
      $overlay = $dummyImages['blurred'];
      $oGravity = 5; // center center
      $oScale = '100%|100%|'; // scale to fit
      $format_category = substr($format, 0, -8);
    } elseif (substr($format, -12) === '-watermarked') {
      $overlay = $dummyImages['watermark'];
      $oGravity = 9; // bottom right
      $oScale = '30%+|auto|'; // do not scale
      $format_category = substr($format, 0, -12);
    }

    if (substr($format_category, -7) === '-retina') {
      $retina = 1;
      $format_category = substr($format_category, 0, -7);
    }

    return [
      'format'       => $format_category,
      '--retina'     => $retina,
      '--blur'       => $blurred,
      '--overlay'    => $overlay,
      '--oGravity'   => $oGravity,
      '--oScale'     => $oScale
    ];
  }

}
