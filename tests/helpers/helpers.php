<?php
if ( ! class_exists( 'WP_Error' ) ) {

    class WP_Error {

        private $errors = [ ];

        function add( $code = '', $message = '', $arguments = '' ) {
            if ( ! isset( $this->errors[$code] ) ) {
                $this->errors[$code] = [ ];
            }
            $this->errors[$code][] = [ 'code' => $code, 'message' => $message, 'data' => $arguments ];
        }

    }
}

if ( ! function_exists( 'apply_filters' ) ) {

    function apply_filters( $filter, $data ) {
        return $data;
    }

}

if ( ! function_exists( 'do_action' ) ) {

    function do_action( $action, $data = [ ] ) {
        return NULL;
    }

}

if ( ! function_exists( 'is_wp_error' ) ) {

    function is_wp_error( $thing ) {
        return is_object( $thing ) && $thing instanceof WP_Error;
    }

}

if ( ! class_exists( 'FaberTestStub' ) ) {

    class FaberTestStub {

    }
}
