<?php

namespace WordPress\DataLiberation\URL;

/**
 * Wrapper around URL parsing that allows swapping the parser via filters.
 */
class WPURL {

    /**
     * Parse a URL and return an URL-like object.
     *
     * A custom parser can be supplied via the `wp_url_parse` filter. When the
     * filter returns a non-null value it is used as the parsing result.
     *
     * @param string|object $url  URL to parse or an existing URL-like object.
     * @param string|null   $base Optional base URL when resolving relative URLs.
     * @return object|false URL-like object on success, false on failure.
     */
    public static function parse( $url, $base = null ) {
        // Allow custom parser to short-circuit.
        $filtered = apply_filters( 'wp_url_parse', null, $url, $base );
        if ( null !== $filtered ) {
            return $filtered;
        }

        // Already parsed object – accept Rowbot URL or our simplified URL.
        if ( is_object( $url ) && ( is_a( $url, '\\Rowbot\\URL\\URL' ) || is_a( $url, __NAMESPACE__ . '\\URL' ) ) ) {
            return $url;
        }

        if ( class_exists( '\\Rowbot\\URL\\URL' ) ) {
            if ( is_string( $url ) ) {
                return \Rowbot\URL\URL::parse( $url, $base ) ?? false;
            }
            return false;
        }

        if ( ! is_string( $url ) ) {
            return false;
        }

        if ( $base && is_string( $base ) ) {
            $url = self::resolve_relative( $url, $base );
        }

        $parts = parse_url( $url );
        if ( false === $parts ) {
            return false;
        }

        $hostname = $parts['host'] ?? '';
        $port     = isset( $parts['port'] ) ? (string) $parts['port'] : '';
        $host     = $hostname;
        if ( $port !== '' ) {
            $host .= ':' . $port;
        }

        return new URL( [
            'protocol' => isset( $parts['scheme'] ) ? $parts['scheme'] . ':' : '',
            'username' => $parts['user'] ?? '',
            'password' => $parts['pass'] ?? '',
            'host'     => $host,
            'hostname' => $hostname,
            'port'     => $port,
            'pathname' => $parts['path'] ?? '',
            'search'   => isset( $parts['query'] ) ? '?' . $parts['query'] : '',
            'hash'     => isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '',
        ] );
    }

    /**
     * Determine whether the given URL can be parsed.
     */
    public static function can_parse( $url, $base = null ) {
        $filtered = apply_filters( 'wp_url_can_parse', null, $url, $base );
        if ( null !== $filtered ) {
            return (bool) $filtered;
        }

        if ( class_exists( '\\Rowbot\\URL\\URL' ) ) {
            return \Rowbot\URL\URL::canParse( $url, $base );
        }

        if ( ! is_string( $url ) ) {
            return false;
        }

        if ( $base && is_string( $base ) ) {
            $url = self::resolve_relative( $url, $base );
        }

        return false !== parse_url( $url );
    }

    /**
     * Resolve a relative URL against a base URL using a very small subset of
     * the WHATWG URL algorithm.
     */
    private static function resolve_relative( $url, $base ) {
        if ( preg_match( '#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url ) ) {
            return $url; // Already absolute.
        }

        $base_parts = parse_url( $base );
        if ( false === $base_parts ) {
            return $url;
        }

        $scheme   = isset( $base_parts['scheme'] ) ? $base_parts['scheme'] . '://' : '';
        $host     = $base_parts['host'] ?? '';
        $port     = isset( $base_parts['port'] ) ? ':' . $base_parts['port'] : '';
        $auth     = '';
        if ( isset( $base_parts['user'] ) ) {
            $auth = $base_parts['user'];
            if ( isset( $base_parts['pass'] ) ) {
                $auth .= ':' . $base_parts['pass'];
            }
            $auth .= '@';
        }
        $authority = $scheme . $auth . $host . $port;

        if ( strpos( $url, '//' ) === 0 ) {
            return $scheme . substr( $url, 2 );
        }

        if ( $url !== '' && $url[0] === '/' ) {
            return $authority . $url;
        }

        $path = $base_parts['path'] ?? '';
        $path = preg_replace( '#/[^/]*$#', '/', $path );
        return $authority . $path . $url;
    }

    /**
     * Prepends a protocol to any matched URL without the double slash.
     *
     * Imagine we have a base URL of `https://example.com` and a text like `Visit myblog.com`.
     * This Processor would match `myblog.com` as a URL candidate and then the parser
     * would parse it as `https://example.com/myblog.com`, which is not what a user would expect.
     *
     * To get `https://myblog.com`, we need to prepend a protocol and turn that candidate into
     * `https://myblog.com` before parsing.
     */
    public static function ensure_protocol( $raw_url, $protocol = 'https' ) {
        if ( ! self::has_double_slash( $raw_url ) ) {
            $raw_url = $protocol . '://' . $raw_url;
        }

        return $raw_url;
    }

    /**
     * This method only considers http and https protocols.
     */
    public static function has_double_slash( $raw_url ) {
        return (
            (
                // Protocol-relative URLs.
                strlen( $raw_url ) > 2 &&
                '/' === $raw_url[0] &&
                '/' === $raw_url[1]
            ) || (
                strlen( $raw_url ) > 7 &&
                ( 'h' === $raw_url[0] || 'H' === $raw_url[0] ) &&
                ( 't' === $raw_url[1] || 'T' === $raw_url[1] ) &&
                ( 't' === $raw_url[2] || 'T' === $raw_url[2] ) &&
                ( 'p' === $raw_url[3] || 'P' === $raw_url[3] ) &&
                ':' === $raw_url[4] &&
                '/' === $raw_url[5] &&
                '/' === $raw_url[6]
            ) || (
                strlen( $raw_url ) > 8 &&
                ( 'h' === $raw_url[0] || 'H' === $raw_url[0] ) &&
                ( 't' === $raw_url[1] || 'T' === $raw_url[1] ) &&
                ( 't' === $raw_url[2] || 'T' === $raw_url[2] ) &&
                ( 'p' === $raw_url[3] || 'P' === $raw_url[3] ) &&
                ( 's' === $raw_url[4] || 'S' === $raw_url[4] ) &&
                ':' === $raw_url[5] &&
                '/' === $raw_url[6] &&
                '/' === $raw_url[7]
            )
        );
    }

    public static function append_path( $base_url, $path ) {
        $base_url           = self::parse( $base_url );
        $base_url->pathname = rtrim( $base_url->pathname, '/' ) . '/' . ltrim( $path, '/' );

        return $base_url->toString();
    }
}
