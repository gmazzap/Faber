<?php namespace GM\Faber\Tests;

class HumanizerTest extends TestCase {

    function testHumanize() {
        $stub = function() {
            return new \FaberTestStub;
        };
        $closure = function() {
            return 'Hello!';
        };
        $faber = \Mockery::mock( 'GM\Faber' )->makePartial();
        $h = spl_object_hash( $faber );
        $prefix = "stub_{$h}";
        $key1 = $prefix . '_' . md5( serialize( [ 'foo' ] ) );
        $key2 = $prefix;
        $key3 = $prefix . '_' . md5( serialize( [ 'foo', 'bar' ] ) );
        $expected = (object) [
                'id'             => 'test_faber',
                'hash'           => $h,
                'properties'     => [
                    'foo'     => 'bar',
                    'bar'     => 'baz',
                    'closure' => '{{Anonymous function}}'
                ],
                'factories'      => [ 'stub' ],
                'cached_objects' => [
                    'stub' => [
                        (object) [ 'key' => $key1, 'class' => 'FaberTestStub' ],
                        (object) [ 'key' => $key2, 'class' => 'FaberTestStub' ],
                        (object) [ 'key' => $key3, 'class' => 'FaberTestStub' ]
                    ]
                ],
                'frozen'         => [ 'foo', 'closure' ]
        ];
        $faber->shouldReceive( 'getContext' )->with( 'frozen' )->andReturn( [ 'foo', 'closure' ] );
        $faber->shouldReceive( 'getContext' )->with( 'context' )->andReturn( [
            'foo'     => 'bar',
            'bar'     => 'baz',
            'stub'    => $stub,
            'closure' => $closure
        ] );
        $faber->shouldReceive( 'getContext' )->with( 'objects' )->andReturn( [
            $key1 => new \FaberTestStub,
            $key2 => new \FaberTestStub,
            $key3 => new \FaberTestStub
        ] );
        $faber->shouldReceive( 'getId' )->withNoArgs()->andReturn( 'test_faber' );
        $faber->shouldReceive( 'isFactory' )->with( 'foo' )->andReturn( FALSE );
        $faber->shouldReceive( 'isFactory' )->with( 'bar' )->andReturn( FALSE );
        $faber->shouldReceive( 'isFactory' )->with( 'stub' )->andReturn( TRUE );
        $faber->shouldReceive( 'isFactory' )->with( 'closure' )->andReturn( FALSE );
        $humanizer = new \GM\Faber\Humanizer( $faber );
        assertEquals( $expected, $humanizer->humanize() );
    }

}