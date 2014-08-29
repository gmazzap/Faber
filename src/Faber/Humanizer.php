<?php namespace GM\Faber;

class Humanizer {

    private $faber;

    private $output;

    private $props = [ ];

    private $objects = [ ];

    private $factories = [ ];

    private $hash = '';

    public function __construct( \GM\Faber $faber = NULL ) {
        if ( ! is_null( $faber ) ) {
            $this->setFaber( $faber );
        }
    }

    public function setFaber( \GM\Faber $faber ) {
        if ( $this->faber !== $faber ) {
            $this->faber = $faber;
            $this->hash = spl_object_hash( $faber );
            $this->output = NULL;
            $this->props = [ ];
            $this->objects = [ ];
            $this->factories = [ ];
        }
        return $this;
    }

    public function getFaber() {
        return $this->faber;
    }

    public function humanize() {
        $this->build();
        return $this->output;
    }

    private function build() {
        if ( empty( $this->output ) ) {
            $this->buildProps();
            $this->buildObjects();
            ksort( $this->factories );
            ksort( $this->objects );
            $this->output = (object) [
                    'id'             => $this->getFaber()->getId(),
                    'hash'           => $this->hash,
                    'properties'     => $this->props,
                    'factories'      => $this->getFaber()->getFactoryIds(),
                    'cached_objects' => $this->objects,
                    'frozen'         => $this->getFaber()->getFrozenIds()
            ];
        }
        return $this->output;
    }

    private function buildProps() {
        $faber = $this->getFaber();
        foreach ( $faber->getPropIds() as $id ) {
            $prop = $faber->prop( $id );
            if ( is_object( $prop ) && $prop instanceof \Closure ) {
                $this->props[$id] = '{{Anonymous function}}';
            } elseif ( is_object( $prop ) && ! $prop instanceof \stdClass ) {
                $this->props[$id] = '{{Instance of: ' . get_class( $prop ) . '}}';
            } else {
                $this->props[$id] = $prop;
            }
        }
    }

    private function buildObjects() {
        foreach ( $this->getFaber()->getObjectsInfo() as $key => $object_info ) {
            $index = $this->getObjectIndex( $key );
            if ( ! isset( $this->objects[$index] ) ) {
                $this->objects[$index] = [ ];
            }
            $this->objects[$index][] = (object) $object_info;
        }
    }

    function getObjectIndex( $key ) {
        if ( empty( $this->hash ) ) {
            return FALSE;
        }
        $index = preg_replace( "#_{$this->hash}.*#", '', $key );
        if ( ! is_serialized( $index ) ) {
            return $index;
        }
        $try = unserialize( $index );
        if ( is_object( $try ) ) {
            $index = '{{Instance of: ' . get_class( $try ) . '}}';
        } elseif ( is_array( $try ) ) {
            $index = '{{Array: ' . implode( ',', $try ) . '}}';
        } elseif ( is_scalar( $try ) ) {
            $index = (string) $try;
        }
        return $index;
    }

}