<?php

namespace TareqAS\Psym\Tests\Fixtures\TabCompletion;

class Baz implements Qux
{
    public function foo()
    {
        return new Foo();
    }

    public function fooBaz(): Foo
    {
        return new Foo();
    }

    /**
     * @return Foo
     */
    public function fooDocBaz()
    {
        return new Foo();
    }
}
