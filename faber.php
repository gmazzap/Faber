<?php
if ( ! class_exists( 'GM\Faber' ) && file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
    require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}
if ( ! class_exists( 'Faber' ) ) {
    class_alias( 'GM\Faber', 'Faber' );
}