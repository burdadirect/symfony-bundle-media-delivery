<?php

namespace HBM\MediaDeliveryBundle\HttpFoundation;


use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomBinaryFileResponse extends BinaryFileResponse {

  /**
   * Sends the file.
   *
   * We use a custom binary file response to support fallback images with other
   * status codes than 2xx/3xx. So we do not use parent::sendContent() in case of 4xx.
   *
   * {@inheritdoc}
   */
  public function sendContent()
  {
    if (0 === $this->maxlen) {
      return $this;
    }

    $out = fopen('php://output', 'wb');
    $file = fopen($this->file->getPathname(), 'rb');

    stream_copy_to_stream($file, $out, $this->maxlen, $this->offset);

    fclose($out);
    fclose($file);

    if ($this->deleteFileAfterSend && file_exists($this->file->getPathname())) {
      unlink($this->file->getPathname());
    }

    return $this;
  }

}
