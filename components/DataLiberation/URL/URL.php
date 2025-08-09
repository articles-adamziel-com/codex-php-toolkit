<?php

namespace WordPress\DataLiberation\URL;

/**
 * Simple URL-like value object used when the full URL parser is not available.
 *
 * Only a subset of the WHATWG URL interface is implemented. The public
 * properties roughly match those from the spec and are populated directly via
 * the constructor. The {@see URLSearchParams} object exposes basic query
 * parameter manipulation.
 */
class URL {
    public $protocol = '';
    public $username = '';
    public $password = '';
    public $host = '';
    public $hostname = '';
    public $port = '';
    public $pathname = '';
    public $search = '';
    public $hash = '';

    /** @var URLSearchParams */
    public $searchParams;

    /**
     * @param array $parts Associative array of URL components.
     */
    public function __construct( array $parts = [] ) {
        foreach ( $parts as $key => $value ) {
            $this->$key = $value;
        }
        $this->searchParams = new URLSearchParams( $this->search, $this );
    }

    /**
     * Returns the URL as a string.
     */
    public function toString() {
        $url = '';
        if ( $this->protocol !== '' ) {
            $url .= rtrim( $this->protocol, ':' ) . ':';
        }
        $authority = '';
        $host      = $this->host !== '' ? $this->host : $this->hostname;
        if ( $host !== '' ) {
            if ( $this->username !== '' ) {
                $authority .= $this->username;
                if ( $this->password !== '' ) {
                    $authority .= ':' . $this->password;
                }
                $authority .= '@';
            }
            $authority .= $host;
        }
        if ( $authority !== '' ) {
            $url .= '//' . $authority;
        }
        $url .= $this->pathname;
        $url .= $this->search;
        $url .= $this->hash;

        return $url;
    }

    public function __toString() {
        return $this->toString();
    }
}
