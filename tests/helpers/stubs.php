<?php namespace GM\Faber\Tests;

class Stub {

    function getAltStub() {
        return new AltStub;
    }

}

class AltStub {

    function getFooStub() {
        return new FooStub;
    }

}

class FooStub {

    function getResult() {
        return 'Result!';
    }

}