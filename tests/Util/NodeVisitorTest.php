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
    public function testToGetKernelClass($version, $expected)
    {
        $code = file_get_contents(__DIR__."/../Fixtures/Visitor/Console/$version.php");

        $ast = $this->parser->parse($code);
        $this->traverser->traverse($ast);

        $this->assertEquals($expected, $this->nodeVisitor->kernelClass);
    }

    private function getSymfonyVersions(): array
    {
        return [
            ['3.4', 'AppKernel'],
            ['4.4', 'App\Kernel'],
            ['5.4', 'App\Kernel'],
            ['custom', 'MyApp\CustomKernel'],
        ];
    }
}
