<?php

namespace TareqAS\Psym;

class SFChecker
{
    private $projectDir;
    private $composer;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getComposer(): array
    {
        if ($this->composer) {
            return $this->composer;
        }

        if (!file_exists($composer = $this->projectDir.'/composer.json')) {
            return [];
        }

        return $this->composer = json_decode(file_get_contents($composer), true);
    }

    public function isSymfonyApp(): bool
    {
        if (!$composer = $this->getComposer()) {
            return false;
        }

        if (!isset($composer['require']['symfony/framework-bundle'])) {
            return false;
        }

        return true;
    }

    public function isSymfony7(): bool
    {
        if (!$this->isSymfonyApp()) {
            return false;
        }

        $composer = $this->getComposer();

        if (isset($composer['require']['symfony/console']) && version_compare($composer['require']['symfony/console'], '7.0', '>=')) {
            return true;
        }

        return false;
    }
}
