<?php

namespace WordPress\DataLiberation\URL;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Minimal implementation of the URLSearchParams interface.
 */
class URLSearchParams implements IteratorAggregate, Countable {
    /** @var array<string, string> */
    private $params = [];
    /** @var URL|null */
    private $parent;

    public function __construct( $query = '', URL $parent = null ) {
        $this->parent = $parent;
        $query        = ltrim( $query, '?' );
        if ( '' !== $query ) {
            parse_str( $query, $this->params );
        }
    }

    public function getIterator() : ArrayIterator {
        return new ArrayIterator( $this->params );
    }

    public function count() : int {
        return count( $this->params );
    }

    public function get( $name ) {
        return $this->params[ $name ] ?? null;
    }

    public function has( $name ) : bool {
        return array_key_exists( $name, $this->params );
    }

    public function set( $name, $value ) : void {
        $this->params[ $name ] = $value;
        $this->update_parent();
    }

    public function delete( $name ) : void {
        unset( $this->params[ $name ] );
        $this->update_parent();
    }

    public function toString() : string {
        return http_build_query( $this->params, '', '&', PHP_QUERY_RFC3986 );
    }

    public function __toString() : string {
        return $this->toString();
    }

    private function update_parent() : void {
        if ( $this->parent ) {
            $query             = $this->toString();
            $this->parent->search = '' === $query ? '' : '?' . $query;
        }
    }
}
