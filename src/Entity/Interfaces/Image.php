<?php

namespace HBM\MediaDeliveryBundle\Entity\Interfaces;

interface Image
{
    public function getId(): int|string;

    public function getFile(): string;

    public function getWidth(): ?int;

    public function getHeight(): ?int;

    public function getCredit(): ?string;

    public function getFSK(): ?int;

    public function useWatermarkedFormat(?User $user = null): bool;

    public function useBlurredFormat(?User $user = null): bool;

    /**
     * Get clipping for a certain format.
     */
    public function getClipping(string $format): array;

    /**
     * Checks if a format hat a custom clipping.
     */
    public function hasClipping(string $format): bool;

    /**
     * Get focal point.
     */
    public function getFocalPoint(): array;

    /**
     * Checks if a focal point is defined.
     */
    public function hasFocalPoint(): bool;
}
