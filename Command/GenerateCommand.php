<?php

namespace HBM\MediaDeliveryBundle\Command;

use Doctrine\Common\Persistence\ObjectManager;
use HBM\MediaDeliveryBundle\Entity\Interfaces\Image;
use HBM\MediaDeliveryBundle\Services\ImageGenerationHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends AbstractCommand
{

  const name = 'hbm:image-delivery:generate';

  protected function configure() {
    $this
      ->setName(GenerateCommand::name)
      ->setDescription('Generate a specific format for an image.')

      ->addArgument('format',     InputArgument::REQUIRED, 'The format to generate.')
      ->addArgument('image',      InputArgument::REQUIRED, 'The id of the image.')
      ->addArgument('path-orig',  InputArgument::REQUIRED, 'The path of the original image.')
      ->addArgument('path-cache', InputArgument::REQUIRED, 'The path of the cached image.')
      ->addOption('retina',   NULL, InputOption::VALUE_NONE, 'Use twice the width and height for resizing.')
      ->addOption('blur',     NULL, InputOption::VALUE_OPTIONAL, 'Blur the image with this value (percent of the sqrt(width*height)).')
      ->addOption('overlay',  NULL, InputOption::VALUE_OPTIONAL, 'Overlay the given image.')
      ->addOption('oGravity', NULL, InputOption::VALUE_OPTIONAL, 'Align the overlay (1 = top left, 2 = top center, ..., 5 = center, ..., 9 = bottom right).')
      ->addOption('oScale',   NULL, InputOption::VALUE_OPTIONAL, 'Scale the overlay (inset = scale to fit, orig = original size).');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    // Enlarge resources
    $this->enlargeResources();

    $config = $this->getContainer()->getParameter('hbm.image_delivery');

    // Arguments
    $image = $input->getArgument('image');
    $format = $input->getArgument('format');
    $path_orig = $input->getArgument('path-orig');
    $path_cache = $input->getArgument('path-cache');

    /** @var Image $imageObj */
    $imageObj = NULL;
    if ($image) {
      /** @var ObjectManager $om */
      $om = $this->getContainer()->get('doctrine')->getManager();
      $repo = $om->getRepository($config['settings']['entity_name']);

      $imageObj = $repo->find($image);
    }

    // Get arguments for default clippings
    $settings = $config['formats'][$format];
    if ($imageObj && $imageObj->hasClipping($format)) {
      $settings['clip'] = $imageObj->getClipping($format);
    }
    $settings['retina'] = $input->getOption('retina');
    $settings['blur'] = $input->getOption('blur');
    $settings['overlay'] = $input->getOption('overlay');
    $settings['oGravity'] = $input->getOption('oGravity');
    $settings['oScale'] = $input->getOption('oScale');

    if (!file_exists($path_orig)) {
      $output->writeln('<cc2error>File not found.</cc2error>');
      return 404;
    }

    $this->getImageGenerationHelper()->generate($path_orig, $path_cache, $settings);

    if ($settings['exif'] && $imageObj) {
      $this->addMetadata($path_cache, $imageObj, $output);
    }

    return 0;
  }

  /**
   * Adds several metadata in exif format to image.
   *
   * @param $path
   * @param \HBM\MediaDeliveryBundle\Entity\Interfaces\Image $image
   * @param \Symfony\Component\Console\Output\OutputInterface|NULL $output
   */
  private function addMetadata($path, Image $image, OutputInterface $output = NULL) {
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
    if ($image->getFSK() >= 18) {
      $parts[] = '-'.$ns.'AdultContentWarning="Adult Content Warning Required"';
    } elseif ($image->getFSK() >= 16) {
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
      // $output->writeln('<cc2note>'.$command.'</cc2note>');
    }
    exec($command);
  }

  /**
   * @return ImageGenerationHelper
   */
  private function getImageGenerationHelper() {
    return $this->getContainer()->get('hbm.helper.image_generation');
  }

}
