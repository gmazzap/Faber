<?php namespace GM\Faber\Tests;

use GM\Faber as F;

class StaticFaberTest extends TestCase {

    function testInstance() {
        $faber = F::instance( 'test' );
        $faber[ 'foo' ] = function() {
            return new Stub;
        };
        $faber2 = F::instance( 'test' );
        $faber3 = F::instance( 'test2' );
        $faber3[ 'foo' ] = function() {
            return new Stub;
        };
        $a = $faber[ 'foo' ];
        $b = $faber[ 'foo' ];
        $c = $faber2[ 'foo' ];
        $d = $faber3[ 'foo' ];
        assertInstanceOf( 'GM\Faber\Tests\Stub', $a );
        assertInstanceOf( 'GM\Faber\Tests\Stub', $b );
        assertInstanceOf( 'GM\Faber\Tests\Stub', $c );
        assertInstanceOf( 'GM\Faber\Tests\Stub', $d );
        assertTrue( $a === $b );
        assertTrue( $b === $c );
        assertFalse( $c === $d );
        assertTrue( $faber === $faber2 );
        assertFalse( $faber2 === $faber3 );
    }

    function testSleepAndWakeUp() {
        $faber = F::instance( 'test' );
        $faber[ 'foo' ] = function() {
            return new Stub;
        };
        $faber[ 'bar' ] = 'baz';
        $faber->protect( 'hello', function() {
            return 'Hello';
        } );
        $foo = $faber[ 'foo' ];
        $bar = $faber[ 'bar' ];
        $hello = $faber[ 'hello' ];
        $sleep = serialize( $faber );
        $wakeup = unserialize( $sleep );
        $foo2 = $wakeup[ 'foo' ];
        $bar2 = $wakeup[ 'bar' ];
        $hello2 = $wakeup[ 'hello' ];
        assertInstanceOf( 'GM\Faber\Tests\Stub', $foo );
        assertInstanceOf( 'Closure', $hello );
        assertTrue( $foo === $foo2 );
        assertTrue( $bar === $bar2 );
        assertTrue( $hello === $hello2 );
    }

}