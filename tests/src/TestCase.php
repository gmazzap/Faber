<?php namespace GM\Faber\Tests;

use Brain\HooksMock\HooksMock as HM;

class TestCase extends \PHPUnit_Framework_TestCase {

    function tearDown() {
        parent::tearDown();
        HM::tearDown();
    }

    protected function getFaber( $id, $things = [ ] ) {
        return new \GM\Faber( $things, $id );
    }

}