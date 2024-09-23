<?php

namespace TareqAS\Psym;

use Psy\Configuration;
use Psy\Shell as PsyShell;
use Symfony\Component\HttpKernel\KernelInterface;

class Shell extends PsyShell
{
    private $kernel;

    public function __construct(Configuration $config = null)
    {
        parent::__construct($config);
    }

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

    public function reset()
    {
        if ($this->getKernel()->getContainer()->has('services_resetter')) {
            $this->getKernel()->getContainer()->get('services_resetter')->reset();
        }
    }
}
