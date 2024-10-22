<?php

namespace TareqAS\Psym;

use Symfony\Component\HttpKernel\KernelInterface;

trait ShellTrait
{
    private $kernel;

    /**
     * Gets the Kernel associated with this Console.
     *
     * @return KernelInterface A KernelInterface instance
     */
    public function getKernel()
    {
        if (!$this->kernel) {
            throw new \RuntimeException('Kernel is not set yet!');
        }

        return $this->kernel;
    }

    public function setKernel($kernel)
    {
        $this->kernel = $kernel;
    }
}
