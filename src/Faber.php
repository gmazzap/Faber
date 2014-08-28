<?php namespace GM;

class Faber implements \ArrayAccess, \JsonSerializable {

    /**
     * @var array
     */
    private static $instances;

    /**
     * @var string
     */
    private $id;

    /**
     * @var array Instantiated objects
     */
    private $objects = [ ];

    /**
     * @var array Properties and facatories
     */
    private $context = [ ];

    /**
     * @var array Closure to be stroed as values and not as factories
     */
    private $protected = [ ];

    /**
     * @var array Properties and objects to protect from being updated or deleted
     */
    private $frozen = [ ];

    /**
     * @var array Prefixes used to generate objects key from factory id
     */
    private $prefixes = [ ];

    /**
     * Retrieve a specific instance of Faber
     *
     * @param mixed $id
     * @return GM\Faber
     */
    static function instance( $id ) {
        $id = $this->maybeSerialize( $id );
        if ( is_null( self::$instances[$id] ) ) {
            $class = get_called_class();
            self::$instances[$id] = new $class( [ ], $id );
        }
        return self::$instances[$id];
    }

    /**
     * Alias for instance()
     *
     * @param mixed $id
     * @return GM\Faber
     */
    static function i( $id ) {
        return static::instance( $id );
    }

    /**
     * Constructor
     *
     * @param array $things Properties / facories to register
     * @param mixed $id Id for the instance, is used as hook prefix and in GM\Faber::i() method
     */
    public function __construct( Array $things = [ ], $id = NULL ) {
        if ( empty( $id ) || ! is_string( $id ) ) {
            $id = uniqid( 'faber_' );
        }
        $this->setId( $id );
        if ( ! empty( $things ) ) {
            $this->load( $things );
        }
        do_action( "{$id}_init", $this );
    }

    public function __destruct() {
        do_action( "{$this->id}_destruct", $this );
    }

    public function __toString() {
        return get_called_class() . ' ' . $this->getId();
    }

    public function __call( $name, $arguments ) {
        if ( strpos( $name, 'get' ) === 0 ) {
            $id = strtolower( substr( $name, 3 ) );
            if ( isset( $this->context[$id] ) ) {
                return $this->get( $id, $arguments );
            }
        } else {
            return $this->error( 'invalid-call', 'Function %s does not exists on Faber', $name );
        }
    }

    public function __set( $name, $value ) {
        return $this->add( $name, $value );
    }

    public function __get( $name ) {
        return $this->get( $name );
    }

    /**
     * Setter for instance ID
     *
     * @param mixed $id
     * @return \GM\Faber
     */
    public function setId( $id ) {
        if ( is_null( $this->id ) ) {
            $id = $this->maybeSerialize( $id );
            $this->id = $id;
        }
        return $this;
    }

    /**
     * Getter for instance id
     *
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Load properties and factories to register from an array
     *
     * @param array $vars
     * @return \GM\Faber
     */
    public function load( Array $vars = [ ] ) {
        foreach ( $vars as $id => $var ) {
            if ( ! is_string( $id ) ) {
                continue;
            }
            $this->register( $id, $var );
        }
        return $this;
    }

    /**
     * Load properties and factories to register from a file that have to return an array
     *
     * @param string $file File full path
     * @return \GM\Faber|\GM\FaberError
     */
    public function loadFile( $file ) {
        if ( file_exists( $file ) ) {
            $vars = @include $file;
            if ( is_array( $vars ) ) {
                $this->load( $vars );
            }
            return $this;
        } else {
            return $this->error( 'wrong-file', 'File to load does not exists.' );
        }
    }

    /**
     * Every built object to be stored has a key that built using factory id and params passed to it.
     * This function generate a key unique for the combination of an id and a set of params.
     *
     * @param mixed $id
     * @param array $args
     * @return string
     */
    function getKey( $id, $args = [ ] ) {
        $id = $this->maybeSerialize( $id );
        $key = $this->keyPrefix( $id );
        if ( ! empty( $args ) ) {
            $key .= '_' . md5( serialize( $args ) );
        }
        return $key;
    }

    /**
     * Add a property or a factory
     *
     * @param mixed $id Id for the property / factory
     * @param mixed $value Value to register. If is a closure will be used as factory
     * @return \GM\Faber
     */
    public function add( $id, $value = NULL ) {
        $id = $this->maybeSerialize( $id );
        if ( ! isset( $this->context[$id] ) ) {
            $this->context[$id] = $value;
        }
        return $this;
    }

    /**
     * Store a Closure to not be used as factory
     *
     * @param mixed $id Id for the closure
     * @param \Closure $value Closure to store
     * @return \GM\Faber
     */
    public function protect( $id, \Closure $value ) {
        $id = $this->maybeSerialize( $id );
        $this->register( $id, $value );
        $this->protected[] = $id;
        return $this;
    }

    /**
     * Retrieve an object from cache or by instatiating it using stored factory
     *
     * @param mixed $id Id for the object factory
     * @param array $args Args passed to factory closure as 2nd param, 1s is the instance of Faber
     * @param string $ensure A class name to match against the retrieved object.
     * @return mixed
     */
    public function get( $id, $args = [ ], $ensure = NULL ) {
        $id = $this->maybeSerialize( $id );
        if ( $this->isProp( $id ) ) {
            return $this->prop( $id );
        }
        $key = $this->getKey( $id, $args );
        if ( ! isset( $this->objects[$key] ) && isset( $this->context[$id] ) ) {
            if ( $this->isFrozen( $id ) && ! $this->isFrozen( $key ) ) {
                $this->frozen[] = $key;
            }
            $model = $this->factory( $id, $args );
            if ( is_wp_error( $model ) ) {
                return $model;
            }
            $this->objects[$key] = $model;
        } elseif ( ! isset( $this->context[$id] ) ) {
            return $this->error( 'wrong-id', 'Factory not defined for the id %s.', $id );
        }
        if (
            is_string( $ensure )
            && ( class_exists( $ensure ) || interface_exists( $ensure ) )
            && ! is_subclass_of( $this->objects[$key], $ensure )
        ) {
            return $this->error( 'wrong-class', 'Retrieved object %s does not match the '
                    . 'desired %s.', [ get_class( $this->objects[$key] ), $ensure ] );
        }
        return apply_filters( "{$this->id}_get", $this->objects[$key] );
    }

    public function getFrozen( $id, $args = [ ], $ensure = NULL ) {
        $key = $this->getKey( $id, $args );
        $object = $this->get( $id, $args, $ensure );
        if ( ! is_wp_error( $object ) ) {
            $this->freeze( $key );
        }
        return $object;
    }

    /**
     * Instantiate a class using a stored factory closure and return it
     *
     * @param mixed $id Id for the factory closure
     * @param array $args Args passed to factory closure as 2nd param, 1s is the instance of Faber
     * @return mixed
     */
    public function factory( $id, $args = [ ] ) {
        $id = $this->maybeSerialize( $id );
        if ( $this->isFactory( $id ) ) {
            return $this->factories[$id]( $this, $args );
        } else {
            return $this->error( 'wrong-id', 'Factory not defined for the id %s.', $id );
        }
    }

    /**
     * Prevent that a stored property, factory or object is modified or unsetted.
     * When a factory is frozen all objects it created an will create are also frozen.
     *
     * @param mixed $id Id of the property / object to freeze
     * @return \GM\Faber
     */
    public function freeze( $id ) {
        $id = $this->maybeSerialize( $id );
        if ( ! isset( $this->context[$id] ) && ! isset( $this->objects[$id] ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        $this->frozen[] = $id;
        if ( isset( $this->objects[$id] ) ) {
            return $this;
        }
        $prefix = $this->keyPrefix( $id );
        array_map( function( $okey ) use($prefix) {
            if ( strpos( $okey, $prefix ) === 0 ) {
                $this->frozen[] = $okey;
            }
        }, array_keys( $this->objects ) );
        $this->frozen = array_unique( $this->frozen );
        return $this;
    }

    /**
     * Remove protection for a property, factory or object previously frozen via GM\Faber::freeze()
     *
     * @param mixed $id Id of the property / object to unfreeze
     * @return \GM\Faber|\GM\FaberError
     */
    public function unfreeze( $id ) {
        $id = $this->maybeSerialize( $id );
        if ( ! isset( $this->context[$id] ) && ! isset( $this->objects[$id] ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        $find = array_search( $id, $this->frozen, TRUE );
        if ( ! $find ) {
            return $this->error( 'wrong-unfreeze-id', 'Nothing to unfreeze.' );
        }
        unset( $this->frozen[$find] );
        if ( isset( $this->objects[$id] ) ) {
            return $this;
        }
        $prefix = $this->keyPrefix( $id );
        $to_unfreeze = [ ];
        array_map( function( $okey ) use($prefix, &$to_unfreeze) {
            if ( strpos( $okey, $prefix ) === 0 ) {
                $to_unfreeze[] = $okey;
            }
        }, array_keys( $this->objects ) );
        if ( ! empty( $to_unfreeze ) ) {
            $this->frozen = array_diff( $this->frozen, $to_unfreeze );
        }
        return $this;
    }

    /**
     * Update a stored property / factory.
     * If a factory is updated, all cached object it created are lost.
     *
     * @param mixed $id Id of the property / factory to update
     * @param mixed $value New value for the property / factory
     * @return \GM\Faber|\GM\FaberError
     */
    public function update( $id, $value = NULL ) {
        $id = $this->maybeSerialize( $id );
        if ( ! isset( $this->context[$id] ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        if ( $this->isFrozen( $id ) ) {
            return $this->error( 'frozen-id', 'Frozed property %s can\'t be updated.', $id );
        }
        if ( $this->context[$id] instanceof \Closure && ! $value instanceof \Closure ) {
            return $this->error( 'bad-value', 'Closure %s can be updated only with another closure.', $id );
        }
        if ( $this->isFactory( $id ) ) {
            $this->remove( $id );
        }
        $this->context[$id] = $value;
        return $this;
    }

    /**
     * Edit a stored object using a closure that receive the object to update and the instance of Faber.
     * Callback must return an instance of the same class of original object.
     *
     * @param string $key Key for the object to extend
     * @param \Closure $callback Callbak that will be used to update the stored object
     * @return \GM\Faber|\GM\FaberError
     */
    public function extend( $key, \Closure $callback ) {
        if ( ! is_string( $key ) ) {
            return $this->error( 'wrong-key', 'Stored object key must me a string.' );
        }
        if ( ! isset( $this->objects[$key] ) ) {
            $orig_key = $key;
            $key = $this->getKey( $key );
        }
        if ( isset( $this->objects[$key] ) && ! $this->isProtected( $key ) ) {
            $class = get_class( $this->objects[$key] );
            $updated = $callback( $this->objects[$key], $this );
            $updated_class = get_class( $updated );
            if ( ! is_object( $updated ) || ( $class !== $updated_class ) ) {
                return $this->error( 'wrong-class', 'Extended object class %s does not match '
                        . 'the original class %s.', [ $updated_class, $class ] );
            }
            $this->objects[$key] = $updated;
        } elseif ( $this->isFrozen( $key ) ) {
            return $this->error( 'frozen-id', 'Object with key %s can\'t be extended '
                    . 'because is frozen.', $key );
        } else {
            return $this->error( 'wrong-id', 'Does not exist any object to extend with '
                    . 'key %s or %s.', [ $orig_key, $key ] );
        }
        return $this;
    }

    /**
     * Remove a property, a factory or an object (by key) from storage.
     * If a factory is removed all the objectt it created are removed as well.
     *
     * @param mixed $id
     * @return \GM\Faber
     */
    public function remove( $id ) {
        $id = $this->maybeSerialize( $id );
        if ( $this->isFrozen( $id ) ) {
            return $this->error( 'frozen-id', 'Forzen property %s can\'t be removed.', $id );
        }
        if ( ! isset( $this->context[$id] ) && ! isset( $this->objects[$id] ) ) {
            return $this->error( 'wrong-id', 'Nothing defined for the id %s.', $id );
        }
        if ( isset( $this->objects[$id] ) ) {
            unset( $this->objects[$id] );
            return $this;
        }
        if ( isset( $this->protected[$id] ) ) {
            unset( $this->protected[$id] );
        }
        unset( $this->context[$id] );
        unset( $this->prefixes[$id] );
        $prefix = $this->keyPrefix( $id );
        array_map( function( $okey ) use($prefix) {
            if ( (strpos( $okey, $prefix ) === 0 ) && ! $this->isFrozen( $okey ) ) {
                unset( $this->objects[$okey] );
            }
        }, array_keys( $this->objects ) );
        return $this;
    }

    /**
     * Get a propery from storage
     *
     * @param mixed $id Id of the property to get
     * @return mixed
     */
    public function prop( $id ) {
        $id = $this->maybeSerialize( $id );
        if ( $this->isProp( $id ) ) {
            return apply_filters( "{$this->id}_get_prop", $this->context[$id] );
        } elseif ( $this->isFactory( $id ) ) {
            return $this->error( 'wrong-prop-id', 'Factory %s can\'t be retrieved as a property. '
                    . 'Use GM\Faber::protect() to store closures as properties.', $id );
        } else {
            return $this->error( 'wrong-prop-id', 'Property not defined for the id %s.', $id );
        }
    }

    /**
     * Error handling for the class. Return an instance of FaberError that extends WP_Error.
     * Thanks to magic __call method this class prevent unexistent methods being called on
     * error objects when uisng fluen interface.
     *
     * @param string $code Code for the error
     * @param string $message Message for the error, can contain sprintf compatible placeholders
     * @param mixed $data If message contain placeholders this contain data for replacements
     * @return \GM\FaberError
     */
    public function error( $code = '', $message = '', $data = NULL ) {
        if ( ! is_string( $code ) || empty( $code ) ) {
            $code = 'unknown';
        }
        $code = 'faber-' . $code;
        if ( ! is_string( $message ) ) {
            $message = $code;
        }
        $data = is_string( $data ) || is_array( $data ) ? (array) $data : NULL;
        if ( ! empty( $message ) && ! empty( $data ) ) {
            $message = vsprintf( $message, $data );
        }
        return new Faber\Error( $code, $message );
    }

    /**
     * Getter for context arrays.
     *
     * @param string $var context variable to access
     * @return array|void
     */
    public function getContext( $var = 'context' ) {
        $vars = [ 'context', 'objects', 'frozen', 'protected', 'prefixes' ];
        return in_array( $var, $vars, TRUE ) ? $this->$var : NULL;
    }

    /**
     * Return an object containing human readable informations about object.
     * Used in Faber::jsonSerialize() for a nice json rapresentation when json encoded.
     *
     * @param \GM\Faber\Humanizer $humanizer
     * @return stdClass
     */
    public function getInfo( Faber\Humanizer $humanizer = NULL ) {
        if ( is_null( $humanizer ) ) {
            $humanizer = new Faber\Humanizer;
        }
        $humanizer->setFaber( $this );
        return $humanizer->humanize();
    }

    /* Issers */

    public function isProtected( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && isset( $this->protected[$id] );
    }

    public function isFrozen( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && isset( $this->frozen[$id] );
    }

    public function isProp( $id ) {
        $id = $this->maybeSerialize( $id );
        return
            is_string( $id )
            && isset( $this->context[$id] )
            && (
            ! $this->context[$id] instanceof \Closure
            || $this->isProtected( $id )
            );
    }

    public function isFactory( $id ) {
        $id = $this->maybeSerialize( $id );
        return
            is_string( $id )
            && isset( $this->context[$id] )
            && $this->context[$id] instanceof \Closure
            && ! $this->isProtected( $id );
    }

    public function isObject( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && isset( $this->objects[$id] );
    }

    /* ArrayAccess */

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

    /* jsonSerializable */

    public function jsonSerialize( Faber\Humanizer $humanizer = NULL ) {
        return $this->getInfo( $humanizer );
    }

    /* Internal stuff */

    private function keyPrefix( $id ) {
        if ( ! isset( $this->prefixes[$id] ) ) {
            $h = spl_object_hash( $this );
            $this->prefixes[$id] = "{$id}_{$h}";
        }
        return $this->prefixes[$id];
    }

    private function maybeSerialize( $id ) {
        if ( ! is_string( $id ) || ! is_serialized( $id ) ) {
            $id = serialize( $id );
        }
        return $id;
    }

}