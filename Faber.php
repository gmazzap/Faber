<?php namespace GM;

class Faber implements \ArrayAccess {

    private static $instances;

    private $id;

    private $objects = [ ];

    private $context = [ ];

    private $protected = [ ];

    private $frozen = [ ];

    static function instance( $id ) {
        $id = maybe_serialize( $id );
        if ( is_null( self::$instances[$id] ) ) {
            $class = get_called_class();
            self::$instances[$id] = new $class( $id );
        }
        return self::$instances[$id];
    }

    public function __construct( $id ) {
        $this->setId( $id );
        do_action( "{$id}_faber_init", $this );
    }

    public function setId( $id ) {
        if ( is_null( $this->id ) ) {
            $id = maybe_serialize( $id );
            $this->id = $id;
        }
    }

    public function getId() {
        return $this->id;
    }

    public function load( Array $vars = [ ] ) {
        foreach ( $vars as $id => $var ) {
            if ( ! is_string( $id ) ) {
                continue;
            }
            $this->register( $id, $var );
        }
    }

    public function loadFromFile( $file ) {
        if ( file_exists( $file ) ) {
            $vars = @include $file;
            if ( is_array( $vars ) ) {
                $this->load( $vars );
            }
        }
    }

    public function register( $id, $value = NULL ) {
        $id = maybe_serialize( $id );
        if ( ! isset( $this->context[$id] ) ) {
            $this->context[$id] = $value;
        }
    }

    public function protect( $id, \Closure $value ) {
        $id = maybe_serialize( $id );
        $this->protected[] = $id;
        $this->register( $id, $value );
    }

    public function freeze( $id ) {
        $id = maybe_serialize( $id );
        $this->frozen[] = $id;
    }

    public function unfreeze( $id ) {
        $id = maybe_serialize( $id );
        $find = array_search( $id, $this->frozen, TRUE );
        if ( ! $find ) {
            return;
        }
        unset( $this->frozen[$find] );
        foreach ( $this->frozen as $i => $key ) {
            if ( preg_match( "#^{$id}_.+$#", $key ) === 1 ) {
                unset( $this->frozen[$i] );
            }
        }
    }

    public function get( $id, $args = [ ] ) {
        $id = maybe_serialize( $id );
        $key = $this->getKey( $id, $args, FALSE );
        if ( ! isset( $this->objects[$key] ) && isset( $this->context[$id] ) ) {
            if ( $id !== $key && in_array( $id, $this->frozen, TRUE ) ) {
                $this->frozen = $key;
            }
            $model = $this->factory( $id, $args );
            $this->objects[$key] = $model;
        }
        return apply_filters( "{$this->id}_faber_get", $this->objects[$key] );
    }

    public function getKey( $id, $args = [ ], $serialize = TRUE ) {
        if ( $serialize ) {
            $id = maybe_serialize( $id );
        }
        return ! empty( $args ) ? $id . '_' . md5( serialize( $args ) ) : $id;
    }

    public function update( $id, $value = NULL ) {
        $id = maybe_serialize( $id );
        if ( in_array( $id, $this->frozen, TRUE ) ) {
            return FALSE;
        }
        $this->context[$id] = $value;
    }

    public function extend( $key, \Closure $callback ) {
        if ( isset( $this->objects[$key] ) && ! in_array( $key, $this->frozen, TRUE ) ) {
            $class = get_class( $this->objects[$key] );
            $updated = $callback( $this->objects[$key], $this );
            if ( ! is_object( $updated ) || ( $class !== get_class( $updated ) ) ) {
                return;
            }
            $this->objects[$key] = $updated;
        }
    }

    public function factory( $id, $args = [ ] ) {
        if ( isset( $this->context[$id] ) && $this->context[$id] instanceof \Closure ) {
            return $this->factories[$id]( $args, $this );
        }
    }

    public function delete( $id, $args = [ ] ) {
        $id = maybe_serialize( $id );
        if ( in_array( $id, $this->frozen, TRUE ) ) {
            return FALSE;
        }
        unset( $this->context[$id] );
        if ( isset( $this->protected[$id] ) ) {
            unset( $this->protected[$id] );
        }
        $key = ! empty( $args ) ? $id . '_' . md5( serialize( $args ) ) : $id;
        if ( isset( $this->objects[$key] ) ) {
            unset( $this->objects[$key] );
        }
    }

    public function getVar( $id ) {
        $id = maybe_serialize( $id );
        if ( isset( $this->context[$id] ) ) {
            return apply_filters( "{$this->id}_faber_get_var", $this->context[$id] );
        }
    }

    public function offsetExists( $offset ) {
        return isset( $this->context[$offset] );
    }

    public function offsetGet( $offset ) {
        if ( isset( $this->context[$offset] ) ) {
            if (
                $this->context[$offset] instanceof \Closure
                && ! in_array( $offset, $this->protected, TRUE )
            ) {
                return $this->get( $offset );
            }
            return $this->getVar( $offset );
        }
    }

    public function offsetSet( $offset, $value ) {
        $this->register( $offset, $value );
    }

    public function offsetUnset( $offset ) {
        $this->delete( $offset );
    }

}