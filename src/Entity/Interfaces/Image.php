<?php

namespace HBM\MediaDeliveryBundle\Entity\Interfaces;

interface Image
{
    public function getId();

    public function getFile();

    public function getWidth();

    public function getHeight();

    public function getCredit();

    public function getFSK();

    /**
     * @return bool
     */
    public function useWatermarkedFormat(User $user = null);

    /**
     * @return bool
     */
    public function useBlurredFormat(User $user = null);

    /**
     * Get clipping for a certain format.
     *
     * @param string $format
     *
     * @return array:
     */
    public function getClipping($format);

    /**
     * Checks if a format hat a custom clipping.
     *
     * @param string $format
     *
     * @return bool
     */
    public function hasClipping($format);

    /**
     * Get focal point.
     *
     * @return array:
     */
    public function getFocalPoint();

    /**
     * Checks if a focal point is defined.
     *
     * @return bool
     */
    public function hasFocalPoint();
}
