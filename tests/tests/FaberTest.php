<?php namespace GM\Faber\Tests;

class FaberTest extends TestCase {

    function testMagicToString() {
        $faber = $this->getFaber( 'foo' );
        assertEquals( "{$faber}", "GM\Faber foo" );
    }

    function testMagicCall() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        assertEquals( $faber->getFoo(), "bar" );
    }

    function testMagicCallError() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        assertInstanceOf( 'WP_Error', $faber->getBar() );
    }

    function testMacicSet() {
        $faber = $this->getFaber( 'foo' );
        $faber->foo = 'bar';
        assertEquals( $faber['foo'], 'bar' );
    }

    function testMacicGet() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        assertEquals( $faber->foo, 'bar' );
    }

    function testSetId() {
        $faber = $this->getFaber( 'foo' );
        $setted = $faber->setId( 'bar' );
        assertEquals( 'foo', $faber->getId() );
        assertTrue( $setted === $faber );
    }

    function testLoad() {
        $faber = $this->getFaber( 'foo' );
        $closure = function() {
            return new \WP_Error;
        };
        $data = [
            'foo'   => 'bar',
            'bar'   => 'baz',
            'error' => $closure
        ];
        $load = $faber->load( $data );
        assertEquals( $faber['foo'], 'bar' );
        assertEquals( $faber['bar'], 'baz' );
        assertInstanceOf( 'WP_Error', $faber['error'] );
        assertTrue( $load === $faber );
    }

    function testLoadFile() {
        $faber = $this->getFaber( 'foo' );
        $load = $faber->loadFile( FABERPATH . '/tests/helpers/array_example.php' );
        assertEquals( $faber['foo'], 'bar' );
        assertEquals( $faber['bar'], 'baz' );
        assertInstanceOf( 'WP_Error', $faber['error'] );
        assertTrue( $load === $faber );
    }

    function testLoadFileError() {
        $faber = $this->getFaber( 'foo' );
        $load = $faber->loadFile( FABERPATH . '/tests/helpers/i-do-not-exists.php' );
        assertInstanceOf( 'WP_Error', $load );
    }

    function testAdd() {
        $faber = $this->getFaber( 'foo' );
        $closure = function() {
            return new \WP_Error;
        };
        $faber->add( 'foo', 'bar' );
        $faber->add( 'bar', 'baz' );
        $faber->add( 'error', $closure );
        $add = $faber->add( 'bar', 'I never exists' );
        assertEquals( $faber['foo'], 'bar' );
        assertEquals( $faber['bar'], 'baz' );
        assertInstanceOf( 'WP_Error', $faber['error'] );
        assertTrue( $add === $faber );
    }

    function testProtect() {
        $faber = $this->getFaber( 'foo' );
        $closure = function() {
            return new \WP_Error;
        };
        $protect = $faber->protect( 'my_closure', $closure );
        assertEquals( $closure, $faber['my_closure'] );
        assertTrue( $protect === $faber );
    }

    function testGetWhenProp() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        assertEquals( $faber->get( 'foo' ), 'bar' );
    }

    function testGetWhenFactory() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub2'] = function( $c, $args = [ ] ) {
            $stub = new \FaberTestStub;
            $stub->foo = $c['foo'];
            $stub->bar = $c['bar'];
            $stub->args = $args;
            $stub->sub_stub = $c['stub'];
            return $stub;
        };
        $faber['stub'] = function( $c, $args = [ ] ) {
            $stub = new \FaberTestStub;
            $stub->foo = $c['foo'];
            $stub->bar = $c['bar'];
            $stub->args = $args;
            return $stub;
        };
        $faber['foo'] = 'bar';
        $faber['bar'] = 'baz';
        $stub = $faber->get( 'stub2', [ 'id' => 'stub2' ] );
        $stub_clone = $faber->get( 'stub2', [ 'id' => 'stub2' ] );
        $stub_alt = $faber->get( 'stub2', [ 'id' => 'stub_alt' ] );
        $stub_alt_clone = $faber->get( 'stub2', [ 'id' => 'stub_alt' ] );
        assertInstanceOf( 'FaberTestStub', $stub );
        assertInstanceOf( 'FaberTestStub', $stub_clone );
        assertInstanceOf( 'FaberTestStub', $stub_alt );
        assertInstanceOf( 'FaberTestStub', $stub->sub_stub );
        assertTrue( $stub->sub_stub === $faber->get( 'stub' ) );
        assertTrue( $stub->sub_stub !== $stub );
        assertEquals( 'stub2', $stub->args['id'] );
        assertEquals( 'stub_alt', $stub_alt->args['id'] );
        assertTrue( $stub_clone === $stub );
        assertTrue( $stub_alt !== $stub );
        assertTrue( $stub_alt_clone === $stub_alt );
    }

    function testGetWhenFactoryAndAssure() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function() {
            return new \FaberTestStub;
        };
        $a = $faber->get( 'stub', [ ], 'FaberTestStub' );
        $b = $faber->get( 'stub', [ ], 'GM\Faber' );
        assertInstanceOf( 'FaberTestStub', $a );
        assertInstanceOf( 'WP_Error', $b );
    }

    function testGetWrongFactory() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function() {
            return new \FaberTestStub;
        };
        $a = $faber->get( 'foo' );
        assertInstanceOf( 'WP_Error', $a );
    }

    function testMake() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function() {
            return new \FaberTestStub;
        };
        $a = $faber->make( 'stub' );
        $b = $faber->make( 'stub' );
        $c = $faber->make( 'stub' );
        assertInstanceOf( 'FaberTestStub', $a );
        assertInstanceOf( 'FaberTestStub', $b );
        assertInstanceOf( 'FaberTestStub', $c );
        assertTrue( $a !== $b );
        assertTrue( $a !== $c );
        assertTrue( $b !== $c );
    }

    function testMakeError() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function() {
            return new \FaberTestStub;
        };
        $a = $faber->make( 'foo' );
        assertInstanceOf( 'WP_Error', $a );
    }

    function testFreeze() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        $faber['bar'] = 'baz';
        $faber['baz'] = 'foo';
        $faber->freeze( 'foo' );
        $freeze = $faber->freeze( 'bar' );
        $faber['foo'] = 'Sad man';
        $faber['bar'] = 'Sad woman';
        $faber['baz'] = 'I am happy';
        assertInstanceOf( 'GM\Faber', $freeze );
        assertEquals( 'bar', $faber['foo'] );
        assertEquals( 'baz', $faber['bar'] );
        assertEquals( 'I am happy', $faber['baz'] );
    }

    function testFreezeError() {
        $faber = $this->getFaber( 'foo' );
        $faber['foo'] = 'bar';
        $freeze = $faber->freeze( 'bar' );
        assertInstanceOf( 'WP_Error', $freeze );
    }

    function testFreezeFactories() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function() {
            $stub = new \FaberTestStub;
            $stub->id = 'stub';
            return $stub;
        };
        $faber['stub2'] = function() {
            $stub = new \FaberTestStub;
            $stub->id = 'stub2';
            return $stub;
        };
        $freeze = $faber->freeze( 'stub' );
        $faber['stub'] = function() {
            $stub = new \FaberTestStub;
            $stub->id = 'updated stub';
            return $stub;
        };
        $faber['stub2'] = function() {
            $stub = new \FaberTestStub;
            $stub->id = 'updated stub2';
            return $stub;
        };
        assertInstanceOf( 'GM\Faber', $freeze );
        assertInstanceOf( 'FaberTestStub', $faber['stub'] );
        assertInstanceOf( 'FaberTestStub', $faber['stub2'] );
        assertEquals( 'stub', $faber['stub']->id );
        assertEquals( 'updated stub2', $faber['stub2']->id );
    }

    function testFreezeObjects() {
        $faber = $this->getFaber( 'foo' );
        $faber['stub'] = function( $f, $args = [ ] ) {
            $stub = new \FaberTestStub;
            $stub->id = 'stub';
            $stub->args = $args;
            return $stub;
        };
        $faber['stub2'] = function( $f, $args = [ ] ) {
            $stub = new \FaberTestStub;
            $stub->id = 'stub2';
            $stub->args = $args;
            return $stub;
        };
        $a = $faber->get( 'stub', [ 'a' ] );
        $faber->freeze( 'stub' );
        $b = $faber->get( 'stub', [ 'b' ] );
        $c = $faber->get( 'stub2', [ 'c' ] );
        assertInstanceOf( 'FaberTestStub', $a );
        assertInstanceOf( 'FaberTestStub', $b );
        assertInstanceOf( 'FaberTestStub', $c );
        $akey = $faber->getKey( 'stub', [ 'a' ] );
        $bkey = $faber->getKey( 'stub', [ 'b' ] );
        $ckey = $faber->getKey( 'stub2', [ 'c' ] );
        assertTrue( $faber->isFrozen( $akey ) );
        assertTrue( $faber->isFrozen( $bkey ) );
        assertFalse( $faber->isFrozen( $ckey ) );
        unset( $faber['stub'] );
        unset( $faber['stub2'] );
        assertInstanceOf( 'FaberTestStub', $faber->get( 'stub' ) );
        assertInstanceOf( 'WP_Error', $faber->get( 'stub2' ) );
    }

}