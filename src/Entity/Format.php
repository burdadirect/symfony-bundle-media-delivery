<?php

namespace HBM\MediaDeliveryBundle\Entity;

/**
 * Defines an image format.
 */
class Format
{
    private string $format;
    private array $configSuffixes;

    private bool $retina = false;
    private bool $blurred = false;
    private bool $watermarked = false;

    public function __construct(string $format, array $configSuffixes)
    {
        $this->format         = $format;
        $this->configSuffixes = $configSuffixes;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    public function isRetina(): bool
    {
        return $this->retina;
    }

    public function setRetina(bool $retina): void
    {
        $this->retina = $retina;
    }

    public function isBlurred(): bool
    {
        return $this->blurred;
    }

    public function setBlurred(bool $blurred): void
    {
        $this->blurred = $blurred;
    }

    public function isWatermarked(): bool
    {
        return $this->watermarked;
    }

    public function setWatermarked(bool $watermarked): void
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
