<?php

namespace TareqAS\Psym\Tests\Fixtures\TabCompletion;

class Foo
{
    public Bar $_bar;

    /**
     * @var Baz
     */
    public $_baz;

    public function __construct()
    {
        $this->_bar = new Bar();
        $this->_baz = new Baz();
    }

    /**
     * @return self
     */
    public static function init()
    {
        return new self();
    }

    /**
     * A sample doc description
     *
     * @return Bar
     */
    public function bar()
    {
        return $this->_bar;
    }

    public function baz(): Baz
    {
        return $this->_baz;
    }

    public function union(): Bar|Baz
    {
    }

    /**
     * @return Bar|Baz
     */
    public function unionDoc()
    {
    }

    public function intersection(): Bar&Baz
    {
    }

    /**
     * @return Bar&Baz
     */
    public function intersectionDoc()
    {
    }

    /**
     * it has no return type
     */
    public function noReturn()
    {
    }
}

function funcFoo(): Foo
{
    return new Foo();
}
