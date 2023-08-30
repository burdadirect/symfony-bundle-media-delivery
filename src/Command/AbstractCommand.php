<?php

namespace HBM\MediaDeliveryBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\Profiler\Profiler;

abstract class AbstractCommand extends Command
{
    /** @var null|Profiler */
    protected $profiler;

    public function setProfiler(?Profiler $profiler): void
    {
        $this->profiler = $profiler;
    }

    /**
     * Enlarge resources.
     */
    protected function enlargeResources(string $memoryLimit = '2G'): void
    {
        // error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        ini_set('memory_limit', $memoryLimit);

        if ($this->profiler) {
            $this->profiler->disable();
        }
    }
}
