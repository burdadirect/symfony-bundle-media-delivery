<?php

namespace HBM\MediaDeliveryBundle\Entity;

/**
 * Defines an image format.
 */
class Format
{
    /** @var array */
    private $configSuffixes;

    /** @var string */
    private $format;

    /** @var bool */
    private $retina = false;

    /** @var bool */
    private $blurred = false;

    /** @var bool */
    private $watermarked = false;

    public function __construct($format, $configSuffixes)
    {
        $this->format         = $format;
        $this->configSuffixes = $configSuffixes;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * @param string $format
     */
    public function setFormat($format): void
    {
        $this->format = $format;
    }

    public function isRetina(): bool
    {
        return $this->retina;
    }

    /**
     * @param bool $retina
     */
    public function setRetina($retina): void
    {
        $this->retina = $retina;
    }

    public function isBlurred(): bool
    {
        return $this->blurred;
    }

    /**
     * @param bool $blurred
     */
    public function setBlurred($blurred): void
    {
        $this->blurred = $blurred;
    }

    public function isWatermarked(): bool
    {
        return $this->watermarked;
    }

    /**
     * @param bool $watermarked
     */
    public function setWatermarked($watermarked): void
    {
        $this->watermarked = $watermarked;
    }

    public function getFormatAdjusted(): string
    {
        $formatString = $this->getFormat();

        if ($this->isBlurred()) {
            $formatString .= $this->configSuffixes['blurred']['format'];
        } elseif ($this->isWatermarked()) {
            $formatString .= $this->configSuffixes['watermarked']['format'];
        }

        if ($this->isRetina()) {
            $formatString .= $this->configSuffixes['retina']['format'];
        }

        return $formatString;
    }

    public function getFormatSuffix(): string
    {
        $formatString = '';

        if ($this->isBlurred()) {
            $formatString .= $this->configSuffixes['blurred']['file'];
        } elseif ($this->isWatermarked()) {
            $formatString .= $this->configSuffixes['watermarked']['file'];
        }

        if ($this->isRetina()) {
            $formatString .= $this->configSuffixes['retina']['file'];
        }

        return $formatString;
    }
}
