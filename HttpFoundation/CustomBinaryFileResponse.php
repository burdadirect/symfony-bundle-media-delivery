<?php

namespace HBM\MediaDeliveryBundle\HttpFoundation;


use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomBinaryFileResponse extends BinaryFileResponse {

  /**
   * Sends the file.
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

    if ($this->deleteFileAfterSend) {
      unlink($this->file->getPathname());
    }

    return $this;
  }

}
