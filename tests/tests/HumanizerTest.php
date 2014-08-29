<?php namespace GM\Faber\Tests;

class HumanizerTest extends TestCase {

    function testHumanize() {
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
                        (object) [ 'key' => $key1, 'class' => 'FaberTestStub', 'num_args' => 1 ],
                        (object) [ 'key' => $key2, 'class' => 'FaberTestStub', 'num_args' => 0 ],
                        (object) [ 'key' => $key3, 'class' => 'FaberTestStub', 'num_args' => 2 ]
                    ]
                ],
                'frozen'         => [ 'foo', 'closure' ]
        ];
        $faber->shouldReceive( 'getId' )->withNoArgs()->andReturn( 'test_faber' );
        $faber->shouldReceive( 'getFrozenIds' )->withNoArgs()->andReturn( [ 'foo', 'closure' ] );
        $faber->shouldReceive( 'getFactoryIds' )->withNoArgs()->andReturn( [ 'stub' ] );
        $faber->shouldReceive( 'getPropIds' )->withNoArgs()->andReturn( [
            'foo', 'bar', 'closure'
        ] );
        $faber->shouldReceive( 'getPropIds' )->withNoArgs()->andReturn( [
            'foo', 'bar', 'closure'
        ] );
        $faber->shouldReceive( 'prop' )->with( 'foo' )->andReturn( 'bar' );
        $faber->shouldReceive( 'prop' )->with( 'bar' )->andReturn( 'baz' );
        $faber->shouldReceive( 'prop' )->with( 'closure' )->andReturn( $closure );
        $faber->shouldReceive( 'getObjectsInfo' )->withNoArgs()->andReturn( [
            $key1 => [ 'key' => $key1, 'class' => 'FaberTestStub', 'num_args' => 1 ],
            $key2 => [ 'key' => $key2, 'class' => 'FaberTestStub', 'num_args' => 0 ],
            $key3 => [ 'key' => $key3, 'class' => 'FaberTestStub', 'num_args' => 2 ]
        ] );
        $humanizer = new \GM\Faber\Humanizer( $faber );
        assertEquals( $expected, $humanizer->humanize() );
    }

}