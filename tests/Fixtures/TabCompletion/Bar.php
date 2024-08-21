<?php

namespace TareqAS\Psym\Tests\Fixtures\TabCompletion;

class Bar implements Qux
{
    /**
     * @inheritdoc
     */
    public function foo()
    {
        return new Foo();
    }

    public function fooBar(): Foo
    {
        return new Foo();
    }

    /**
     * @return Foo
     */
    public function fooDocBar()
    {
        return new Foo();
    }
}
