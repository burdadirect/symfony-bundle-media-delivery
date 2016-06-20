<?php

namespace HBM\ImageDeliveryBundle\Command;

use HBM\ImageDeliveryBundle\Entity\Interfaces\Deliverable;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Imagine\Image\Point;

class ImagesGenerateFormatCommand extends ImagesGenerateFormatAbstractCommand
{

  protected $serialExecution  = FALSE;
  protected $asyncExecution   = FALSE;
  protected $noLogging        = FALSE;
  protected $enlargeResources = TRUE;

	protected function configure() {
		$this
		->setName('pbp:images:generate-format')
		->setDescription('Generate a specific format for a images.')

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

	protected function executeLogic(InputInterface $input, OutputInterface $output) {
    // Get arguments for custom clippings
    $format = $input->getArgument('format');
    $image = $input->getArgument('image');
    $custom = $input->getOption('custom');

    $imageObj = NULL;
    if ($image) {
      /** @var \Doctrine\ORM\EntityManager $em */
      $em = $this->getContainer()->get('doctrine')->getManager();
      $repo = $em->getRepository('PBYCyberclubBundle:MediaImage');

      $imageObj = $repo->find($image);
    }

    // Get arguments for default clippings
    $formats = $this->getContainer()->getParameter('pby_image_formats');

    $settings = $formats[$format];
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
   * @param \HBM\ImageDeliveryBundle\Entity\Interfaces\Deliverable $image
   */
  private function addMetadata($path, Deliverable $image, OutputInterface $output = NULL) {
    /**************************************************************************/

    //$title = 'Bild. | Image.';
    //$description = 'Bild aus einer Galerie. | Image of a gallery.';
    $company = 'Playboy Deutschland Publishing GmbH';
    $companyShort = 'Playboy Deutschland';
    $product = 'PlayboyPremium';
    $url = 'https://premium.playboy.de';
    $notice = 'Die Verwendung, Verteilung und Verbreitung ist untersagt. | Reuse, distribution and dissemination is prohibited.';
    $email = 'premium@playboy.de';
    $telephone = '+49 89 / 9250-0';
    $contact = 'Mail: '.$email.' - Phone: '.$telephone;

    $parts = [];

    /**************************************************************************/

    // IPTC
    $ns = 'IPTC:';
    $parts[] = '-'.$ns.'Credit="'.$companyShort.'"';
    $parts[] = '-'.$ns.'Source="'.$url.'"';
    $parts[] = '-'.$ns.'CopyrightNotice="'.$notice.'"';
    $parts[] = '-'.$ns.'Contact="'.$contact.'"';

    /**************************************************************************/

    // XMP (pur)
    $ns = 'XMP-pur:';
    $parts[] = '-'.$ns.'Agreement="'.$notice.'"';
    $parts[] = '-'.$ns.'Copyright="'.$company.'"';
    $parts[] = '-'.$ns.'CreditLine="'.$product.'"';
    $parts[] = '-'.$ns.'Permissions="'.$notice.'"';
    $parts[] = '-'.$ns.'ReuseProhibited="1"';

    /**************************************************************************/

    // XMP (prism)
    $ns = 'XMP-prism:';
    // Not suported in version 9.46
    //$parts[] = '-'.$ns.'CopyrightYear="'.date('Y').'"';

    /**************************************************************************/

    // XMP (dc)
    $ns = 'XMP-dc:';
    //$parts[] = escapeshellarg('-'.$ns.'Title="'.$title.'"');
    //$parts[] = escapeshellarg('-'.$ns.'Description="'.$description.'"');

    $parts[] = '-'.$ns.'Creator="'.$company.'"';
    $parts[] = '-'.$ns.'Publisher="'.$company.'"';
    $parts[] = '-'.$ns.'Rights="'.$notice.'"';

    /**************************************************************************/

    // XMP (xmpRights)
    $ns = 'XMP-xmpRights:';
    $parts[] = '-'.$ns.'Owner="'.$company.'"';
    $parts[] = '-'.$ns.'UsageTerms="'.$notice.'"';
    $parts[] = '-'.$ns.'WebStatement="'.$url.'"';

    /**************************************************************************/

    // XMP-Ext
    $ns = 'XMP-iptcExt:';
    $parts[] = '-'.$ns.'ArtworkSource="'.$product.'"';
    $parts[] = '-'.$ns.'ArtworkSourceInventoryNo="'.$image->getId().'"';
    // Not suported in version 9.46
    //$parts[] = '-'.$ns.'ArtworkSourceInvURL="'.$url.'"';
    $parts[] = '-'.$ns.'ArtworkCopyrightNotice="'.$notice.'"';

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

    $parts[] = '-'.$ns.'CopyrightOwnerName="'.$company.'"';
    $parts[] = '-'.$ns.'CopyrightStatus="Protected"';
    $parts[] = '-'.$ns.'CreditLineRequired="Credit Adjacent To Image"';

    $parts[] = '-'.$ns.'ImageCreatorName="'.$image->getCredit().'"';
    $parts[] = '-'.$ns.'ImageType="Photographic Image"';

    $parts[] = '-'.$ns.'LicensorName="'.$company.'"';
    $parts[] = '-'.$ns.'LicensorCity="Munich"';
    $parts[] = '-'.$ns.'LicensorCountry="Germany"';
    $parts[] = '-'.$ns.'LicensorStreetAddress="Arabellastrasse 23"';
    $parts[] = '-'.$ns.'LicensorPostalCode="81925"';
    $parts[] = '-'.$ns.'LicensorRegion="BY"';
    $parts[] = '-'.$ns.'LicensorEmail="'.$email.'"';
    $parts[] = '-'.$ns.'LicensorTelephone1="'.$telephone.'"';
    $parts[] = '-'.$ns.'LicensorTelephoneType1="work"';
    $parts[] = '-'.$ns.'LicensorURL="'.$url.'"';

    /**************************************************************************/

    // XMP-Ext (iptcCore)
    $ns = 'XMP-iptcCore:';
    $parts[] = '-'.$ns.'CountryCode="DE"';
    $parts[] = '-'.$ns.'CreatorCity="Munich"';
    $parts[] = '-'.$ns.'CreatorCountry="Germany"';
    $parts[] = '-'.$ns.'CreatorAddress="Arabellastrasse 23"';
    $parts[] = '-'.$ns.'CreatorPostalCode="81925"';
    $parts[] = '-'.$ns.'CreatorRegion="BY"';
    $parts[] = '-'.$ns.'CreatorWorkEmail="'.$email.'"';
    $parts[] = '-'.$ns.'CreatorWorkTelephone="'.$telephone.'"';
    $parts[] = '-'.$ns.'CreatorWorkURL="'.$url.'"';

    /**************************************************************************/

    $command = 'exiftool -overwrite_original '.implode(' ', $parts).' '.escapeshellarg($path);
    if ($output) {
      //$output->writeln('<cc2note>'.$command.'</cc2note>');
    }
    exec($command);
  }

  public static function determineParameters($format, $folderDummy) {
    $retina = 0;
    $blurred = 0;
    $overlay = FALSE;
    $oGravity = FALSE;
    $oScale = FALSE;
    $format_suffix = '';
    $format_category = $format;

    if (substr($format, -8) === '-blurred') {
      $blurred = 5;
      $overlay = $folderDummy.'bunnyFSK.png';
      $oGravity = 5; // center center
      $oScale = '100%|100%|'; // scale to fit
      $format_suffix = '_blurred';
      $format_category = substr($format, 0, -8);
    } elseif (substr($format, -12) === '-watermarked') {
      $overlay = $folderDummy.'bunnyLogo.png';
      $oGravity = 9; // bottom right
      $oScale = '30%+|auto|'; // do not scale
      $format_suffix = '_watermarked';
      $format_category = substr($format, 0, -12);
    }

    if (substr($format_category, -7) === '-retina') {
      $retina = 1;
      $format_suffix = $format_suffix.'__retina';
      $format_category = substr($format_category, 0, -7);
    }

    return [
      'suffix' => $format_suffix,
      'args' => [
        'format'       => $format_category,
        '--retina'     => $retina,
        '--blur'       => $blurred,
        '--overlay'    => $overlay,
        '--oGravity'   => $oGravity,
        '--oScale'     => $oScale
      ]
    ];
  }

}
