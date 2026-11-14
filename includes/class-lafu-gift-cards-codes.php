<?php
/**
 * Gift card codes and lookup rate limits.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates unguessable codes and limits how fast anyone can try them.
 */
class Lafu_Gift_Cards_Codes {

	/**
	 * No 0/O or 1/I/L, so codes read back correctly from paper.
	 */
	const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/**
	 * Failed lookups allowed per visitor in one window.
	 */
	const MAX_FAILURES = 10;

	/**
	 * Failed lookups allowed from one IP address in one window, for all
	 * visitors on it together. Higher, because many shoppers can share one
	 * address (an office, a mobile network, a proxy).
	 */
	const MAX_IP_FAILURES = 100;

	/**
	 * Length of the failure window.
	 */
	const WINDOW = 15 * MINUTE_IN_SECONDS;

	/**
	 * A new code: optional prefix plus four groups of four characters,
	 * about 79 bits of randomness.
	 *
	 * @return string
	 */
	public static function generate() {
		$max    = strlen( self::ALPHABET ) - 1;
		$groups = array();
		for ( $g = 0; $g < 4; $g++ ) {
			$group = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$group .= self::ALPHABET[ random_int( 0, $max ) ];
			}
			$groups[] = $group;
		}
		$prefix = Lafu_Gift_Cards_Settings::code_prefix();
		return ( '' !== $prefix ? $prefix . '-' : '' ) . implode( '-', $groups );
	}

	/**
	 * The form used for lookups: upper case letters and digits only, so
	 * spaces, dashes and lower case typed by a customer do not matter.
	 *
	 * @param string $code Code as typed or printed.
	 * @return string
	 */
	public static function normalize( $code ) {
		return (string) preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $code ) );
	}

	/**
	 * Code with only the last group visible, for receipts and lists.
	 *
	 * @param string $code Full code.
	 * @return string
	 */
	public static function mask( $code ) {
		$tail = substr( self::normalize( $code ), -4 );
		return '•••• ' . $tail;
	}

	/**
	 * Whether this visitor has used up the failed lookups for now: either
	 * this visitor alone, or everyone on the same IP address together.
	 *
	 * @return bool
	 */
	public static function is_limited() {
		if ( (int) get_transient( self::visitor_key() ) >= self::MAX_FAILURES ) {
			return true;
		}
		/**
		 * Failed lookups allowed from one IP address in 15 minutes.
		 *
		 * @param int $max Failures.
		 */
		$ip_max = (int) apply_filters( 'lafu_gift_cards_ip_max_failures', self::MAX_IP_FAILURES );
		return (int) get_transient( self::ip_key() ) >= max( self::MAX_FAILURES, $ip_max );
	}

	/**
	 * Count one failed lookup for this visitor and for the IP address.
	 */
	public static function record_failure() {
		$keys = array_unique( array( self::visitor_key(), self::ip_key() ) );
		foreach ( $keys as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, self::WINDOW );
		}
	}

	/**
	 * The visitor's IP address. Forwarded headers count only when the
	 * request comes from a trusted proxy (by default private and loopback
	 * addresses, such as a reverse proxy on the same network). Cloudflare is
	 * not trusted unless the shop adds its ranges with the
	 * lafu_gift_cards_trusted_proxies filter.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? self::valid_ip( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		/**
		 * Proxies whose forwarded headers are trusted: IP addresses or CIDR ranges.
		 *
		 * @param string[] $ranges Ranges.
		 */
		$trusted = (array) apply_filters( 'lafu_gift_cards_trusted_proxies', array( '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7' ) );
		if ( '' === $remote || ! self::in_ranges( $remote, $trusted ) ) {
			return $remote;
		}
		// Walk the chain from the nearest hop back: the first address that is
		// not a trusted proxy is the visitor. Earlier entries can be forged.
		$chain = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) : array();
		foreach ( array_reverse( $chain ) as $hop ) {
			$hop = self::valid_ip( trim( $hop ) );
			if ( '' === $hop ) {
				break;
			}
			if ( ! self::in_ranges( $hop, $trusted ) ) {
				return $hop;
			}
			$remote = $hop;
		}
		if ( ! $chain ) {
			foreach ( array( 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP' ) as $header ) {
				$value = isset( $_SERVER[ $header ] ) ? self::valid_ip( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) : '';
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return $remote;
	}

	/**
	 * Transient key for the current visitor: the account when logged in,
	 * the shopping session for a guest with one, else the IP address.
	 *
	 * @return string
	 */
	private static function visitor_key() {
		$user = get_current_user_id();
		if ( $user ) {
			$who = 'u' . $user;
		} elseif ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'has_session' ) && WC()->session->has_session() ) {
			$who = 's' . WC()->session->get_customer_id();
		} else {
			$who = 'ip' . self::client_ip();
		}
		return 'lafu_gc_rl_' . md5( $who );
	}

	/**
	 * Transient key for everyone on the visitor's IP address.
	 *
	 * @return string
	 */
	private static function ip_key() {
		return 'lafu_gc_rl_' . md5( 'ip' . self::client_ip() );
	}

	/**
	 * A valid IP address, or an empty string.
	 *
	 * @param string $ip Address.
	 * @return string
	 */
	private static function valid_ip( $ip ) {
		$ip = (string) filter_var( trim( (string) $ip ), FILTER_VALIDATE_IP );
		return false === strpos( $ip, ':' ) ? $ip : strtolower( $ip );
	}

	/**
	 * Whether an address is in any of the ranges (IPv4 or IPv6 CIDR, or a
	 * single address).
	 *
	 * @param string   $ip     Address.
	 * @param string[] $ranges Ranges.
	 * @return bool
	 */
	public static function in_ranges( $ip, array $ranges ) {
		$addr = inet_pton( $ip );
		if ( false === $addr ) {
			return false;
		}
		foreach ( $ranges as $range ) {
			$parts = explode( '/', trim( (string) $range ), 2 );
			$net   = filter_var( $parts[0], FILTER_VALIDATE_IP ) ? inet_pton( $parts[0] ) : false;
			if ( false === $net || strlen( $net ) !== strlen( $addr ) ) {
				continue;
			}
			$bits = isset( $parts[1] ) ? min( 8 * strlen( $net ), max( 0, (int) $parts[1] ) ) : 8 * strlen( $net );
			$full = intdiv( $bits, 8 );
			if ( substr( $addr, 0, $full ) !== substr( $net, 0, $full ) ) {
				continue;
			}
			$rest = $bits % 8;
			if ( 0 === $rest || ( ( ord( $addr[ $full ] ) ^ ord( $net[ $full ] ) ) & ( 0xff << ( 8 - $rest ) ) & 0xff ) === 0 ) {
				return true;
			}
		}
		return false;
	}
}
