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
	 * Whether this visitor has used up the failed lookups for now.
	 *
	 * @return bool
	 */
	public static function is_limited() {
		return (int) get_transient( self::limit_key() ) >= self::MAX_FAILURES;
	}

	/**
	 * Count one failed lookup for this visitor.
	 */
	public static function record_failure() {
		$key = self::limit_key();
		set_transient( $key, (int) get_transient( $key ) + 1, self::WINDOW );
	}

	/**
	 * Transient key for the current visitor. Logged-in users are counted
	 * per account, guests per IP address.
	 *
	 * @return string
	 */
	private static function limit_key() {
		$user = get_current_user_id();
		if ( $user ) {
			$who = 'u' . $user;
		} else {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$who = 'ip' . $ip;
		}
		return 'lafu_gc_rl_' . md5( $who );
	}
}
