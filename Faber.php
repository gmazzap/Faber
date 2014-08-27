<?php namespace GM;

class Faber implements \ArrayAccess {

    private static $instances;

    private $id;

    private $objects = [ ];

    private $context = [ ];

    private $protected = [ ];

    private $frozen = [ ];

    static function i( $id ) {
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
        return $this;
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
        return $this;
    }

    public function loadFile( $file ) {
        if ( file_exists( $file ) ) {
            $vars = @include $file;
            if ( is_array( $vars ) ) {
                $this->load( $vars );
            }
            return $this;
        } else {
            return $this->error( 'wrong-file', 'File to load does not exists' );
        }
    }

    public function register( $id, $value = NULL ) {
        $id = maybe_serialize( $id );
        if ( ! isset( $this->context[$id] ) ) {
            $this->context[$id] = $value;
        }
        return $this;
    }

    public function protect( $id, \Closure $value ) {
        $id = maybe_serialize( $id );
        $this->protected[] = $id;
        $this->register( $id, $value );
        return $this;
    }

    public function freeze( $id ) {
        $id = maybe_serialize( $id );
        $this->frozen[] = $id;
        return $this;
    }

    public function unfreeze( $id ) {
        $id = maybe_serialize( $id );
        $find = array_search( $id, $this->frozen, TRUE );
        if ( ! $find ) {
            return $this->error( 'wrong-unfreeze-id', 'Nothing to unfreeze.' );
        }
        unset( $this->frozen[$find] );
        foreach ( $this->frozen as $i => $key ) {
            if ( preg_match( "#^{$id}_.+$#", $key ) === 1 ) {
                unset( $this->frozen[$i] );
            }
        }
        return $this;
    }

    public function get( $id, $args = [ ], $ensure = NULL ) {
        $id = maybe_serialize( $id );
        $key = $this->getKey( $id, $args, FALSE );
        if ( ! isset( $this->objects[$key] ) && isset( $this->context[$id] ) ) {
            if ( $id !== $key && in_array( $id, $this->frozen, TRUE ) ) {
                $this->frozen = $key;
            }
            $model = $this->factory( $id, $args );
            $this->objects[$key] = $model;
        } elseif ( ! isset( $this->context[$id] ) ) {
            return $this->error( 'wrong-id', 'Factory not defined for the id %s', $id );
        }
        if ( is_string( $ensure ) && class_exists( $ensure ) && ! is_a( $this->objects[$key], $ensure ) ) {
            return $this->error( 'wrong-class', 'Retrieved object %s does not match the desired %s', [ get_class( $this->objects[$key] ), $ensure ] );
        }
        return apply_filters( "{$this->id}_faber_get", $this->objects[$key] );
    }

    public function factory( $id, $args = [ ] ) {
        $id = maybe_serialize( $id );
        if ( isset( $this->context[$id] ) && $this->context[$id] instanceof \Closure ) {
            return $this->factories[$id]( $this, $args );
        } else {
            return $this->error( 'wrong-id', 'Factory not defined for the id %s', $id );
        }
    }

    public function update( $id, $value = NULL ) {
        $id = maybe_serialize( $id );
        if ( in_array( $id, $this->frozen, TRUE ) ) {
            return;
        }
        $this->context[$id] = $value;
        return $this;
    }

    public function extend( $key, \Closure $callback ) {
        if ( isset( $this->objects[$key] ) && ! in_array( $key, $this->frozen, TRUE ) ) {
            $class = get_class( $this->objects[$key] );
            $updated = $callback( $this->objects[$key], $this );
            $updated_class = get_class( $updated );
            if ( ! is_object( $updated ) || ( $class !== $updated_class ) ) {
                return $this->error( 'wrong-class', 'Extended object class %s does not match the original class %s', [ $updated_class, $class ] );
            }
            $this->objects[$key] = $updated;
        } elseif ( ! isset( $this->objects[$key] ) ) {
            return $this->error( 'wrong-id', 'Does not exist any objct to extend with key %s', $key );
        }
        return $this;
    }

    public function remove( $id, $args = [ ] ) {
        $id = maybe_serialize( $id );
        if ( in_array( $id, $this->frozen, TRUE ) ) {
            return;
        }
        unset( $this->context[$id] );
        if ( isset( $this->protected[$id] ) ) {
            unset( $this->protected[$id] );
        }
        $key = ! empty( $args ) ? $id . '_' . md5( serialize( $args ) ) : $id;
        if ( isset( $this->objects[$key] ) ) {
            unset( $this->objects[$key] );
        }
        return $this;
    }

    public function prop( $id ) {
        $id = maybe_serialize( $id );
        if (
            isset( $this->context[$id] )
            && ( ! $this->context[$id] instanceof \Closure || in_array( $id, $this->protected, TRUE ) )
        ) {
            return apply_filters( "{$this->id}_faber_prop", $this->context[$id] );
        } elseif ( ! isset( $this->context[$id] ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s', $id );
        }
    }

    public function getKey( $id, $args = [ ], $serialize = TRUE ) {
        if ( $serialize ) {
            $id = maybe_serialize( $id );
        }
        return ! empty( $args ) ? $id . '_' . md5( serialize( $args ) ) : $id;
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
            return $this->prop( $offset );
        }
    }

    public function offsetSet( $offset, $value ) {
        $this->register( $offset, $value );
    }

    public function offsetUnset( $offset ) {
        $this->delete( $offset );
    }

    public function error( $code = '', $message = '', $data = NULL ) {
        if ( ! is_string( $code ) || empty( $code ) ) {
            $code = 'generic';
        }
        $code = 'faber-' . $code;
        if ( ! is_string( $message ) ) {
            $message = $code;
        }
        $data = is_string( $data ) || is_array( $data ) ? (array) $data : NULL;
        if ( ! empty( $message ) && ! empty( $data ) ) {
            $message = vsprintf( $message, $data );
        }
        return new FaberError( $code, $message );
    }

}