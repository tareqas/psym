<?php

namespace TareqAS\Psym;

use Psy\Configuration;
use Psy\Shell as PsyShell;

class Shell7 extends PsyShell
{
    use ShellTrait;

    public function __construct(Configuration $config = null)
    {
        parent::__construct($config);
    }

    public function reset(): void
    {
        if ($this->getKernel()->getContainer()->has('services_resetter')) {
            $this->getKernel()->getContainer()->get('services_resetter')->reset();
        }
    }
}
