<?php namespace GM\Faber;

class Error extends \WP_Error implements \ArrayAccess {

    function __call( $name, $arguments ) {
        $code = "faber-call-on-error-{$name}";
        $message = "The function {$name} was called on an error object";
        $this->add( $code, $message, $arguments );
        return $this;
    }

    function __toString() {
        return 'ERROR: ' . $this->get_error_message();
    }

    public function offsetExists( $offset ) {
        $code = "faber-call-on-error-{$offset}";
        $message = "Tried to check for {$offset} property on an error object";
        $this->add( $code, $message );
        return FALSE;
    }

    public function offsetGet( $offset ) {
        $code = "faber-call-on-error-{$offset}";
        $message = "Tried to get {$offset} from an error object";
        $this->add( $code, $message );
        return $this;
    }

    public function offsetSet( $offset, $value ) {
        $code = "faber-call-on-error-{$offset}";
        $message = "Tried to set {$offset} on an error object";
        $this->add( $code, $message, $value );
    }

    public function offsetUnset( $offset ) {
        $code = "faber-call-on-error-{$offset}";
        $message = "Tried to unset {$offset} from an error object";
        $this->add( $code, $message );
    }

}