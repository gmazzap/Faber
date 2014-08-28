<?php namespace GM\Faber;

use GM\Faber as F;

class Humanizer {

    private $faber;

    private $output;

    private $props;

    private $objects;

    private $factories;

    private $hash;

    public function __construct( F $faber = NULL ) {
        if ( ! is_null( $faber ) ) {
            $this->setFaber( $faber );
        }
    }

    public function setFaber( F $faber ) {
        if ( $this->faber !== $faber ) {
            $this->faber = $faber;
            $this->hash = spl_object_hash( $faber );
            $this->output = NULL;
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
                    'factories'      => $this->factories,
                    'cached_objects' => $this->objects,
                    'frozen'         => $this->getFaber()->getContext( 'frozen' )
            ];
        }
        return $this->output;
    }

    private function buildProps() {
        $faber = $this->getFaber();
        foreach ( $faber->getContext( 'context' ) as $id => $prop ) {
            if ( $faber->isFactory( $id ) ) {
                $this->factories[] = $id;
            } elseif ( is_object( $prop ) && $prop instanceof \Closure ) {
                $this->props[$id] = '{{Anonymous function}}';
            } elseif ( is_object( $prop ) && ! $prop instanceof \stdClass ) {
                $this->props[$id] = '{{Instance of: ' . get_class( $prop ) . '}}';
            } else {
                $this->props[$id] = $prop;
            }
        }
    }

    private function buildObjects() {
        foreach ( $this->objects as $key => $object ) {
            $index = $this->getObjectIndex( $key );
            if ( ! isset( $this->objects[$index] ) ) {
                $this->objects[$index] = [ ];
            }
            $this->objects[$index][] = (object) [ 'key' => $key, 'class' => get_class( $object ) ];
        }
    }

    private function getObjectIndex( $key ) {
        $index = preg_replace( "#_{$this->hash}.*#", '', $key );
        if ( ! is_serialized( $index ) ) {
            return $index;
        }
        $try = unserialize( $index );
        if ( is_object( $try ) ) {
            $index = '{{Instance of: ' . get_class( $try ) . '}}';
        } elseif ( is_array( $try ) ) {
            $index = '{{Array: ' . implode( ',', $try ) . '}}';
        } elseif ( ! is_scalar( $try ) ) {
            $index = '{{Non scalar value (' . $index . ')}}';
        }
        return $index;
    }

}