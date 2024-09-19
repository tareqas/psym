<?php

namespace TareqAS\Psym\Tests\Util;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TareqAS\Psym\Parser\NodeVisitor;

class NodeVisitorTest extends TestCase
{
    private $parser;
    private $traverser;
    private $nodeVisitor;

    protected function setUp(): void
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
        $this->nodeVisitor = new NodeVisitor();
        $this->traverser->addVisitor($this->nodeVisitor);
    }

    /**
     * @dataProvider getSymfonyVersions
     */
    public function testToGetKernelClass($version, $kernel, $doesItReturnClosure)
    {
        $code = file_get_contents(__DIR__."/../Fixtures/Visitor/Console/$version.php");

        $ast = $this->parser->parse($code);
        $this->traverser->traverse($ast);

        $this->assertSame($kernel, $this->nodeVisitor->kernelClass);
        $this->assertSame($doesItReturnClosure, $this->nodeVisitor->doesItReturnClosure);
    }

    private function getSymfonyVersions(): array
    {
        return [
            ['3.4', 'AppKernel', false],
            ['4.4', 'App\Kernel', false],
            ['5.4', 'App\Kernel', true],
            ['custom', 'MyApp\CustomKernel', true],
        ];
    }
}
