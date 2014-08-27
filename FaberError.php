<?php namespace GM;

class FaberError extends \WP_Error {

    function __call( $name, $arguments ) {
        $code = "faber-call-on-error-{$name}";
        $message = "The function {$name} was called on an error object";
        $this->add( $code, $message, $arguments );
        return $this;
    }

}