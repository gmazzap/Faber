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
     * @var string
     */
    private $hash;

    /**
     * @var array Instantiated objects
     */
    private $objects = [ ];

    /**
     * @var array Instantiated objects info
     */
    private $objects_info = [ ];

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
     * @param array $things Properties / factories to add
     * @param mixed $id Id for the instance, is used as hook prefix and in GM\Faber::i() method
     */
    public function __construct( Array $things = [ ], $id = NULL ) {
        if ( empty( $id ) || ! is_string( $id ) ) {
            $id = uniqid( 'faber_' );
        }
        $this->id = $id;
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
            if ( $this->offsetExists( $id ) ) {
                return $this->get( $id, $arguments );
            }
        }
        return $this->error( 'invalid-call', 'Function %s does not exists on Faber', $name );
    }

    public function __set( $name, $value ) {
        return $this->add( $name, $value );
    }

    public function __get( $name ) {
        return $this->get( $name );
    }

    public function __sleep() {
        return [ 'id', 'hash' ];
    }

    public function __wakeup() {
        if ( ! isset( self::$instances[$this->id] ) ) {
            return;
        }
        $this->context = self::$instances[$this->id]->context;
        $this->objects = self::$instances[$this->id]->objects;
        $this->objects_info = self::$instances[$this->id]->objects_info;
        foreach ( $this->context as $value ) {
            if ( is_object( $value ) && $value instanceof Closure ) {
                $value->bindTo( $this );
            }
        }
        $this->frozen = self::$instances[$this->id]->frozen;
        $this->protected = self::$instances[$this->id]->protected;
        $this->prefixes = self::$instances[$this->id]->prefixes;
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
     * Getter for instance hash
     *
     * @return string
     */
    public function getHash() {
        if ( is_null( $this->hash ) ) {
            $this->hash = spl_object_hash( $this );
        }
        return $this->hash;
    }

    /**
     * Load properties and factories to add from an array
     *
     * @param array $vars
     * @return \GM\Faber
     */
    public function load( Array $vars = [ ] ) {
        foreach ( $vars as $id => $var ) {
            $this->add( $id, $var );
        }
        return $this;
    }

    /**
     * Load properties and factories to add from a file that have to return an array
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
        }
        return $this->error( 'wrong-file', 'File to load does not exists.' );
    }

    /**
     * Every built object to be stored has a key built using factory id and params passed to it.
     * This function generate a key unique for the combination of an id and a set of params.
     *
     * @param mixed $id
     * @param array $args
     * @return string
     */
    function getObjectKey( $id, Array $args = [ ] ) {
        $id = $this->maybeSerialize( $id );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
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
     * @param mixed $value Value to add. If is a closure will be used as factory
     * @return \GM\Faber
     */
    public function add( $id, $value = NULL ) {
        $id = $this->maybeSerialize( $id );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( ! $this->offsetExists( $id ) ) {
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        $this->protected[] = $id;
        $this->add( $id, $value );
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( $this->isProp( $id ) ) {
            return apply_filters( "{$this->id}_get_prop", $this->context[$id] );
        } elseif ( $this->isFactory( $id ) ) {
            return $this->error( 'wrong-prop-id', 'Factory %s can\'t be retrieved as a property. '
                    . 'Use GM\Faber::protect() to store closures as properties.', $id );
        }
        return $this->error( 'wrong-prop-id', 'Property not defined for the id %s.', $id );
    }

    /**
     * Retrieve an object from cache or by instatiating it using stored factory
     *
     * @param mixed $id Id for the object factory
     * @param array $args Args passed to factory closure as 2nd param, 1s is the instance of Faber
     * @param string $ensure A class name to match against the retrieved object.
     * @return mixed
     */
    public function get( $id, Array $args = [ ], $ensure = NULL ) {
        $id = $this->maybeSerialize( $id );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( $this->isProp( $id ) ) {
            return $this->prop( $id );
        }
        $key = $this->getObjectKey( $id, $args );
        if ( ! $this->isCachedObject( $key ) && $this->offsetExists( $id ) ) {
            if ( $this->isFrozen( $id ) && ! $this->isFrozen( $key ) ) {
                $this->frozen[] = $key;
            }
            $object = $this->make( $id, $args );
            if ( is_wp_error( $object ) ) {
                return $object;
            }
            $this->objects[$key] = $object;
            $this->objects_info[$key] = [
                'key'      => $key,
                'class'    => get_class( $object ),
                'num_args' => count( $args )
            ];
        } elseif ( ! $this->offsetExists( $id ) ) {
            return $this->error( 'wrong-id', 'Factory not defined for the id %s.', $id );
        }
        if (
            is_string( $ensure )
            && ( class_exists( $ensure ) || interface_exists( $ensure ) )
            && (
            ! is_a( $this->objects[$key], $ensure )
            && ! is_subclass_of( $this->objects[$key], $ensure )
            )
        ) {
            return $this->error( 'wrong-class', 'Retrieved object %s does not match the '
                    . 'desired %s.', [ get_class( $this->objects[$key] ), $ensure ] );
        }
        return apply_filters( "{$this->id}_get", $this->objects[$key] );
    }

    public function getAndFreeze( $id, Array $args = [ ], $ensure = NULL ) {
        $object = $this->get( $id, $args, $ensure );
        if ( ! is_wp_error( $object ) ) {
            $key = $this->getObjectKey( $id, $args );
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
    public function make( $id, Array $args = [ ] ) {
        $id = $this->maybeSerialize( $id );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        return ( $this->isFactory( $id ) ) ?
            $this->context[$id]( $this, $args ) :
            $this->error( 'wrong-id', 'Factory not defined for the id %s.', $id );
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( ! $this->offsetExists( $id ) && ! $this->isCachedObject( $id ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        $this->frozen[] = $id;
        if ( $this->isCachedObject( $id ) ) {
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( ! $this->offsetExists( $id ) && ! $this->isCachedObject( $id ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        if ( ! $this->isFrozen( $id ) ) {
            return $this->error( 'wrong-unfreeze-id', 'Nothing to unfreeze.' );
        }
        $find = array_search( $id, $this->frozen, TRUE );
        unset( $this->frozen[$find] );
        if ( $this->isCachedObject( $id ) ) {
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( ! $this->offsetExists( $id ) ) {
            return $this->error( 'wrong-id', 'Property not defined for the id %s.', $id );
        }
        if ( $this->isFrozen( $id ) ) {
            return $this->error( 'frozen-id', 'Frozed property %s can\'t be updated.', $id );
        }
        if ( $this->context[$id] instanceof \Closure && ! $value instanceof \Closure ) {
            return $this->error( 'bad-value', 'Closures can be updated only with closures.' );
        }
        if ( $this->isFactory( $id ) ) {
            $this->remove( $id );
        }
        $this->context[$id] = $value;
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
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        if ( ! $this->offsetExists( $id ) && ! $this->isCachedObject( $id ) ) {
            return $this->error( 'wrong-id', 'Nothing defined for the id %s.', $id );
        }
        if ( $this->isFrozen( $id ) ) {
            return $this->error( 'frozen-id', 'Frozen property %s can\'t be removed.', $id );
        }
        if ( $this->isCachedObject( $id ) ) {
            unset( $this->objects[$id] );
            unset( $this->objects_info[$id] );
            return $this;
        }
        if ( isset( $this->protected[$id] ) ) {
            unset( $this->protected[$id] );
        }
        $prefix = $this->keyPrefix( $id );
        unset( $this->context[$id] );
        unset( $this->prefixes[$id] );
        array_map( function( $okey ) use($prefix) {
            if ( (strpos( $okey, $prefix ) === 0 ) && ! $this->isFrozen( $okey ) ) {
                unset( $this->objects[$okey] );
                unset( $this->objects_info[$okey] );
            }
        }, array_keys( $this->objects ) );
        return $this;
    }

    /**
     * Get the ids for the registered factories
     *
     * @return array
     */
    public function getFactoryIds() {
        return array_values( array_filter( array_keys( $this->context ), [ $this, 'isFactory' ] ) );
    }

    /**
     * Get the ids for the stored properties
     *
     * @return arra
     */
    public function getPropIds() {
        return array_values( array_diff( array_keys( $this->context ), $this->getFactoryIds() ) );
    }

    /**
     * Get the ids for frozen entities (properties, factories, objects)
     *
     * @return arra
     */
    public function getFrozenIds() {
        return $this->frozen;
    }

    /**
     * Returns info on the current cached objects
     *
     * @return array
     */
    public function getObjectsInfo() {
        return $this->objects_info;
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

    /**
     * Error handling for the class. Return an instance of GM\Faber\Error that extends WP_Error.
     * Thanks to magic __call method this class prevent unexistent methods being called on
     * error objects when using fluent interface.
     *
     * @param string $code Code for the error
     * @param string $message Message for the error, can contain sprintf compatible placeholders
     * @param mixed $data If message contain placeholders this var contain data for replacements
     * @return \GM\Faber\Error
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

    /* Issers */

    public function isProtected( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && in_array( $id, $this->protected, TRUE );
    }

    public function isFrozen( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && in_array( $id, $this->frozen, TRUE );
    }

    public function isProp( $id ) {
        $id = $this->maybeSerialize( $id );
        return is_string( $id ) && $this->offsetExists( $id ) && ! $this->isFactory( $id );
    }

    public function isFactory( $id ) {
        $id = $this->maybeSerialize( $id );
        return
            is_string( $id )
            && $this->offsetExists( $id )
            && ( is_object( $this->context[$id] ) && $this->context[$id] instanceof \Closure )
            && ! $this->isProtected( $id );
    }

    public function isCachedObject( $key ) {
        return is_string( $key ) && isset( $this->objects[$key] );
    }

    /* ArrayAccess */

    public function offsetExists( $offset ) {
        return isset( $this->context[$offset] );
    }

    public function offsetGet( $offset ) {
        return $this->get( $offset );
    }

    public function offsetSet( $offset, $value ) {
        if ( ! $this->offsetExists( $offset ) ) {
            $this->add( $offset, $value );
        } else {
            $this->update( $offset, $value );
        }
    }

    public function offsetUnset( $offset ) {
        $this->remove( $offset );
    }

    /* jsonSerializable */

    public function jsonSerialize() {
        return $this->getInfo();
    }

    /* Internal stuff */

    private function keyPrefix( $id ) {
        if ( ! isset( $this->prefixes[$id] ) ) {
            $h = $this->getHash();
            $this->prefixes[$id] = "{$id}_{$h}";
        }
        return $this->prefixes[$id];
    }

    private function maybeSerialize( $id ) {
        if (
            is_null( $id ) || is_bool( $id ) || is_resource( $id )
            || ( is_object( $id ) && $id instanceof \Closure )
        ) {
            return $this->error( 'bad-id', 'Only strings, numerics, arrays and objects'
                    . ' (but not closures) can be used as id.' );
        }
        try {
            return is_scalar( $id ) ? (string) $id : serialize( $id );
        } catch ( Exception $e ) {
            return $this->error( 'bad-id', 'Only serializable vars can be used as id.' );
        }
    }

}