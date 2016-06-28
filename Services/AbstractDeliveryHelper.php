<?php
namespace HBM\MediaDeliveryBundle\Services;

/**
 * Service
 *
 * Makes image delivery easy.
 */
abstract class AbstractDeliveryHelper {

  /**
   * Calculates time and duration for hmac signature.
   * If duration is preceeded with a ~, an aproximated value is used.
   *
   * @param string|integer $duration
   * @return array
   */
  public function getTimeAndDuration($duration) {
    $time = time();

    $time_to_use = $time;
    $duration_to_use = $duration;
    if (substr($duration, 0, 1) === '~') {
      $duration_to_use = substr($duration, 1);

      $time_to_use = $time;
      if ($duration_to_use > 100000) {
        $time_to_use = round($time / 10000) * 10000;
      } elseif ($duration_to_use > 10000) {
        $time_to_use = round($time / 1000) * 1000;
      } elseif ($duration_to_use > 1000) {
        $time_to_use = round($time / 100) * 100;
      } elseif ($duration_to_use > 100) {
        $time_to_use = round($time / 10) * 10;
      }
    }

    return [
      'time' => intval($time_to_use),
      'duration' => intval($duration_to_use)
    ];
  }

}
