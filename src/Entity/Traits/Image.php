<?php

namespace HBM\MediaDeliveryBundle\Entity\Traits;

use HBM\MediaDeliveryBundle\Entity\Interfaces\User;

trait Image
{
    public function getId(): int|string
    {
        return '-';
    }

    public function getWidth(): ?int
    {
        return null;
    }

    public function getHeight(): ?int
    {
        return null;
    }

    public function getCredit(): ?string
    {
        return null;
    }

    public function getFSK(): ?int
    {
        return null;
    }

    public function useWatermarkedFormat(?User $user = null): bool
    {
        return false;
    }

    public function useBlurredFormat(?User $user = null): bool
    {
        return false;
    }

    public function getClipping(string $format): array
    {
        return [];
    }

    public function hasClipping(string $format): bool
    {
        return false;
    }

    public function getFocalPoint(): array
    {
        return [];
    }

    public function hasFocalPoint(): bool
    {
        return false;
    }
}
