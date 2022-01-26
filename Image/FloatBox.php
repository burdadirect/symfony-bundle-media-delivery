<?php

namespace HBM\MediaDeliveryBundle\Image;

use Imagine\Image\BoxInterface;
use Imagine\Image\PointInterface;
use Imagine\Image\Point;

class FloatBox implements BoxInterface {

  private float $width;

  private float $height;

  /**
   * Constructs the Size with given width and height
   *
   * @param float $width
   * @param float $height
   *
   * @throws \InvalidArgumentException
   */
  public function __construct(float $width, float $height) {
    $this->width  = $width;
    $this->height = $height;
  }

  public function getWidth(): int {
    return round($this->width);
  }

  public function getWidthFloat(): float {
    return $this->width;
  }

  public function getHeight(): int {
    return round($this->height);
  }

  public function getHeightFloat(): float {
    return $this->height;
  }

  public function scale($ratio): self {
    return new FloatBox($ratio * $this->width, $ratio * $this->height);
  }

  public function increase($size): self {
    return new FloatBox((float) $size + $this->width, (float) $size + $this->height);
  }

  public function contains(BoxInterface $box, PointInterface $start = null): bool {
    $start = $start ?: new Point(0, 0);

    return $start->in($this) && $this->width >= $box->getWidth() + $start->getX() && $this->height >= $box->getHeight() + $start->getY();
  }

  public function square(): int {
    return $this->width * $this->height;
  }

  public function __toString(): string {
    return sprintf('%dx%d px', $this->width, $this->height);
  }

  public function widen($width): self {
    return $this->scale($width / $this->width);
  }

  public function heighten($height): self {
    return $this->scale($height / $this->height);
  }

}
