<?php namespace GM\Faber\Tests;

class TestCase extends \PHPUnit_Framework_TestCase {

    protected function tearDown() {
        \GM\Faber::flushInstances();
        \Mockery::close();
    }

}