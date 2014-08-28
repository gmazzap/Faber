<?php namespace GM\Faber\Tests;

class FaberTest extends TestCase {

    function testToString() {
        $faber = $this->getFaber( 'foo' );
        assertEquals( "{$faber}", "GM\Faber foo" );
    }

}