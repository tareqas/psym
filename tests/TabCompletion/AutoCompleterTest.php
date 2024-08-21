<?php

namespace TareqAS\Psym\Tests\TabCompletion;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psy\Context;
use Psy\ContextAware;
use TareqAS\Psym\Parser\SqlParser;
use TareqAS\Psym\TabCompletion\AutoCompleter;
use TareqAS\Psym\Tests\Fixtures\TabCompletion\Foo;
use TareqAS\Psym\Util\DoctrineEM;

class AutoCompleterTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @dataProvider classesInput
     */
    public function testClassesCompletion(string $line, array $expected, string $name = null)
    {
        $context = new Context();

        $commands = [
            new \TareqAS\Psym\Command\ListEntitiesCommand(),
        ];

        $matchers = [
            new \TareqAS\Psym\TabCompletion\Matcher\SqlFunctionMatcher(),
            new \TareqAS\Psym\TabCompletion\Matcher\MethodChainingMatcher(),
            new \Psy\TabCompletion\Matcher\CommandsMatcher($commands),
        ];

        $tabCompletion = new AutoCompleter();
        foreach ($matchers as $matcher) {
            if ($matcher instanceof ContextAware) {
                $matcher->setContext($context);
            }
            $tabCompletion->addMatcher($matcher);
        }

        $context->setAll(['foo' => new Foo()]);

        $mock = Mockery::mock('alias:'.DoctrineEM::class);
        $mock->shouldReceive('getTables')
            ->andReturn(['table1', 'table2', 'table3']);
        $mock->shouldReceive('getColumns')
            ->andReturn(['c1', 'c2', 'c3']);

        $code = $tabCompletion->processCallback('', 0, [
           'line_buffer' => $line,
           'point' => 0,
           'end' => \strlen($line),
        ]);

        self::assertSame($expected, $code);
    }

    private function classesInput(): array
    {
        return [
            /* MethodChainingMatcher */
            ['$tmp = foo', ['foo()']],

            ['foo ', [$this->getDocAndSignature('foo'), '']],
            ['foo(', [$this->getDocAndSignature('foo'), '']],
            ['foo($bar', ['bar']], // add extra space at the end 'foo($bar '
            ['foo($bar, baz()->', [$this->getDocAndSignature('foo'), '']],
            // It should display doc with signature. However, it currently returns the same string because of:
            // - interference with another matcher, and
            // - a problem with displaying on the console.
            ['foo(baz()', ['foo(baz()']],
            ['foo(baz() ', [$this->getDocAndSignature('foo'), '']],

            // It should display both 'union' and 'unionDoc'. However, it currently only displays 'union'.
            // ['foo()->union', ['union()', 'unionDoc()']],
            ['foo()->bar', ['bar()']],
            ['foo()->ba', ['bar()', 'baz()', '_bar', '_baz']],

            ['foo()->noReturn() ', [$this->getDocAndSignature('noReturn'), '']],
            ['foo()->doesNotExist->', ["\n ** unknown: foo()->[37;41mdoesNotExist[39;49m->\n", '']],

            ['foo()->bar()->', ['foo()', 'fooBar()', 'fooDocBar()']],
            ['foo()->bar->', ["\n ** Invalid method closing: foo()->[37;41mbar[39;49m->\n", '']],

            ['mi', ['']],
            ['min', ['min()']],
            ['min() ', [$this->getDocAndSignature('min'), '']],
            ['\TareqAS\Psym\Tests\Fixtures\TabCompletion\funcFoo()->_bar->', ['foo()', 'fooBar()', 'fooDocBar()']],
            ['TareqAS\Psym\Tests\Fixtures\TabCompletion\funcFoo()->_baz->', ['foo()', 'fooBaz()', 'fooDocBaz()']],
            ['\TareqAS\Psym\Tests\Fixtures\TabCompletion\Foo::init()->_bar->foo()->_baz->', ['foo()', 'fooBaz()', 'fooDocBaz()']],
            ['TareqAS\Psym\Tests\Fixtures\TabCompletion\Foo::init()->_bar->foo()->_baz->', ['foo()', 'fooBaz()', 'fooDocBaz()']],
            ['$foo->bar()->', ['foo()', 'fooBar()', 'fooDocBar()']],
            ['$foo->_baz->', ['foo()', 'fooBaz()', 'fooDocBaz()']],
            ['$tmp = $foo->_bar->', ['foo()', 'fooBar()', 'fooDocBar()']],
            ['$foo->bar(\'hi\', $foo->bar(), foo(\'there\'))->', ['foo()', 'fooBar()', 'fooDocBar()']],
            ['$foo->union()->', ['foo()', 'fooBar()', 'fooDocBar()', 'fooBaz()', 'fooDocBaz()']],
            ['$foo->unionDoc()->', ['foo()', 'fooBar()', 'fooDocBar()', 'fooBaz()', 'fooDocBaz()']],
            ['$foo->intersection()->', ['foo()']],
            ['$foo->intersectionDoc()->', ['foo()']],
            ['$foo->bar()->foo() ', [$this->getDocAndSignature('inheritdoc'), '']],

            /* SqlFunctionMatcher */
            ['sql(', SqlParser::$keywordsStarting],
            ['sql(\'selec', ['select']],
            ['sql("selec', ['select']],
            ['sql(`selec', ['select']],
            ['sql("select * from', ['table1', 'table2', 'table3']],
            ['sql("select * from tab', ['table1', 'table2', 'table3']],
            ['sql("select t1. from table1 t1', ['t1.c1', 't1.c2', 't1.c3']],
            ['sql("select t1.c from table1 t1', ['t1.c1', 't1.c2', 't1.c3']],
            ['sql("select t1. from table1 t1 join table2 t2 on t2. = t1.c1', ['t1.c1', 't1.c2', 't1.c3', 't2.c1', 't2.c2', 't2.c3']],
            ['sql("select t1.c1 from table1 t1', ['t1']],
            ['sql("select t1.c1 from table1 t1 ', SqlParser::$keywords],
            ['sql("select t1.c1 from table1 t1 jo', ['join']],
            ['sql()', ['sql()']],
            ['sql("select *, (select t2.c2 from table2 t2 limit 1) from table1")', ['sql("select *, (select t2.c2 from table2 t2 limit 1) from table1")']],
            ['sql("invalid sql', ['table1', 'table2', 'table3']],
        ];
    }

    private function getDocAndSignature($name): string
    {
        $docs = [
            'foo' => <<<'DOC'
[32m/**
 * A global user-defined function for testing
 *
 * [39m[35m@return[39m[32m Foo
 */[39m
[36mfunction[39m [31mfoo[39m()

DOC,
            'inheritdoc' => <<<'DOC'
[32m/**
 * You're following inheritdoc to get me
 *
 * [39m[35m@return[39m[32m Foo
 */[39m
[36mpublic function[39m [31mfoo[39m()

DOC,
            'min' => <<<'DOC'
[32m/**
 * Find lowest value
 * [39m[35m@link[39m[32m https://php.net/manual/en/function.min.php
 * [39m[35m@param[39m[32m array|mixed [39m[31m$value[39m[32m Array to look through or first value to compare
 * [39m[35m@param[39m[32m mixed ...[39m[31m$values[39m[32m any comparable value
 * [39m[35m@return[39m[32m mixed min returns the numerically lowest of the
 * parameter values.
 */[39m
[36mfunction[39m [31mmin[39m(?[36mmixed [39m[31m$value[39m, ?[36mmixed [39m...[31m$values[39m)

DOC,
            'noReturn' => <<<'DOC'
[32m/**
 * it has no return type
 */[39m
[36mpublic function[39m [31mnoReturn[39m()

DOC,
        ];

        return $docs[$name];
    }
}
