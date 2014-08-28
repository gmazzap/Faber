<?php namespace GM\Faber\Tests;

class TestCase extends \PHPUnit_Framework_TestCase {

    protected function getFaber( $id, $things = [ ] ) {
        return new \GM\Faber( $things, $id );
    }

}