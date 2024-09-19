<?php

namespace TareqAS\Psym;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Runtime\SymfonyRuntime;
use TareqAS\Psym\Parser\NodeVisitor;

class SFLoader
{
    private $projectDir;
    private $usefulServices;
    private $kernelInstance;
    private $nodeVisitor;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getUsefulServices(): array
    {
        if ($this->usefulServices) {
            return $this->usefulServices;
        }

        $kernel = $this->getKernelInstance();
        $container = $kernel ? $kernel->getContainer() : null;
        $doctrine = $container->has('doctrine') ? $container->get('doctrine') : null;
        $em = $doctrine ? $doctrine->getManager() : null;

        $GLOBALS['kernel'] = $kernel;
        $GLOBALS['container'] = $container;
        $GLOBALS['doctrine'] = $doctrine;
        $GLOBALS['em'] = $em;

        return $this->usefulServices = [$kernel, $container, $doctrine, $em];
    }

    public function getAllCommands(): array
    {
        if (!$kernel = $this->getKernelInstance()) {
            return [];
        }

        $app = new Application($kernel);

        return $app->all();
    }

    public function getKernelInstance()
    {
        $kernel = null;

        if ($this->kernelInstance) {
            return $this->kernelInstance;
        }

        try {
            if (!file_exists($console = $this->projectDir.'/bin/console')) {
                throw new \RuntimeException('Boot failed: bin/console file not found!');
            }

            if (file_exists($this->projectDir.'/vendor/autoload_runtime.php') && $this->hasRuntimeBeenUsedInConsole($console)) {
                $runtime = new SymfonyRuntime(['project_dir' => $this->projectDir]);
                $app = require $console;
                [$app, $args] = $runtime->getResolver($app)->resolve();
                $app = $app(['APP_ENV' => $args[0]['APP_ENV'] ?? 'dev', 'APP_DEBUG' => (bool) $args[0]['APP_DEBUG'] ?? true]);
                $kernel = $app->getKernel();
                $kernel->boot();

                return;
            }

            if (file_exists($bootstrap = $this->projectDir.'/config/bootstrap.php')) {
                require $bootstrap;
            } else {
                require __DIR__.'/bootstrap.php';
            }

            if (!class_exists($kernelClass = $this->getKernelClass($console))) {
                if (!class_exists($kernelClass = 'App\Kernel')) {
                    if (!class_exists($kernelClass = 'AppKernel')) {
                        throw new \RuntimeException('Boot failed: kernel class not found!');
                    }
                }
            }

            $kernel = new $kernelClass($_SERVER['APP_ENV'] ?? 'dev', $_SERVER['APP_DEBUG'] ?? true);
            $kernel->boot();
        } catch (\Throwable $error) {
            $this->displayWarning($error->getMessage());
        } finally {
            if ($kernel && !$kernel->getContainer()->has('doctrine')) {
                $this->displayWarning('Doctrine not found');
            }

            return $this->kernelInstance = $kernel;
        }
    }

    private function hasRuntimeBeenUsedInConsole(string $console): bool
    {
        $nodeVisitor = $this->getVisitedNodeVisitor($console);

        return $nodeVisitor->doesItReturnClosure;
    }

    private function getKernelClass(string $console): ?string
    {
        $nodeVisitor = $this->getVisitedNodeVisitor($console);

        return $nodeVisitor->kernelClass;
    }

    private function getVisitedNodeVisitor(string $file): NodeVisitor
    {
        if ($this->nodeVisitor) {
            return $this->nodeVisitor;
        }
        $code = file_get_contents($file);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $nodeVisitor = new NodeVisitor();
        $traverser->addVisitor($nodeVisitor);
        $traverser->traverse($ast);

        return $this->nodeVisitor = $nodeVisitor;
    }

    private function displayWarning(string $message): void
    {
        echo "\n\033[1;31m * $message\033[0m\n\n";
    }
}
