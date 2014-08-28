<?php
$closure = function() {
    return new \WP_Error;
};
return [
    'foo'   => 'bar',
    'bar'   => 'baz',
    'error' => $closure
];
