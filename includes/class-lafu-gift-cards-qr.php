<?php
/**
 * Small QR code encoder for gift card codes.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encodes short text (byte mode, error correction level M, versions 1 to 6,
 * up to 106 bytes) into a QR matrix, then draws it as SVG or as an email
 * safe HTML table. Self-contained, so no image service sees the codes.
 */
class Lafu_Gift_Cards_Qr {

	/**
	 * Per version at level M: [data codewords per block, number of blocks,
	 * EC codewords per block, alignment pattern centre].
	 */
	const VERSIONS = array(
		1 => array( 16, 1, 10, 0 ),
		2 => array( 28, 1, 16, 18 ),
		3 => array( 44, 1, 26, 22 ),
		4 => array( 32, 2, 18, 26 ),
		5 => array( 43, 2, 24, 30 ),
		6 => array( 27, 4, 16, 34 ),
	);

	/**
	 * GF(256) exponent table.
	 *
	 * @var int[]
	 */
	private static $exp = array();

	/**
	 * GF(256) log table.
	 *
	 * @var int[]
	 */
	private static $log = array();

	/**
	 * QR matrix for a text: rows of booleans (true = dark), or null when the
	 * text is too long.
	 *
	 * @param string $text Text to encode.
	 * @return array|null
	 */
	public static function matrix( $text ) {
		$best = null;
		$low  = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$grid = self::matrix_with_mask( $text, $mask );
			if ( null === $grid ) {
				return null;
			}
			$score = self::penalty( $grid, count( $grid ) );
			if ( $score < $low ) {
				$low  = $score;
				$best = $grid;
			}
		}
		return $best;
	}

	/**
	 * QR matrix with a fixed mask pattern (0 to 7). matrix() picks the best.
	 *
	 * @param string $text Text to encode.
	 * @param int    $mask Mask pattern.
	 * @return array|null
	 */
	public static function matrix_with_mask( $text, $mask ) {
		$bytes   = '' === (string) $text ? array() : array_values( unpack( 'C*', (string) $text ) );
		$version = 0;
		foreach ( self::VERSIONS as $v => $spec ) {
			if ( count( $bytes ) + 2 <= $spec[0] * $spec[1] ) {
				$version = $v;
				break;
			}
		}
		if ( ! $version ) {
			return null;
		}
		list( $per_block, $blocks, $ec_len, $align ) = self::VERSIONS[ $version ];

		$codewords               = self::interleave( self::data_codewords( $bytes, $per_block * $blocks ), $per_block, $blocks, $ec_len );
		$size                    = 17 + 4 * $version;
		list( $grid, $reserved ) = self::function_patterns( $size, $align );
		self::place_data( $grid, $reserved, $codewords, (int) $mask, $size );
		self::place_format( $grid, (int) $mask, $size );
		return $grid;
	}

	/**
	 * QR code as an SVG element.
	 *
	 * @param string $text  Text to encode.
	 * @param int    $px    Rendered width in pixels.
	 * @param string $color Dark module colour.
	 * @return string SVG markup, or an empty string.
	 */
	public static function svg( $text, $px = 120, $color = '#1a1a1a' ) {
		$grid = self::matrix( $text );
		if ( ! $grid ) {
			return '';
		}
		$size = count( $grid );
		$view = $size + 8;
		$path = '';
		foreach ( $grid as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$path .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="%1$d" height="%1$d" fill="#ffffff"/><path d="%3$s" fill="%4$s"/></svg>',
			(int) $view,
			(int) $px,
			esc_attr( $path ),
			esc_attr( $color )
		);
	}

	/**
	 * QR code as an HTML table, which every email client shows (many strip
	 * SVG and block images).
	 *
	 * @param string $text   Text to encode.
	 * @param int    $module Module size in pixels.
	 * @return string HTML, or an empty string.
	 */
	public static function html_table( $text, $module = 3 ) {
		$grid = self::matrix( $text );
		if ( ! $grid ) {
			return '';
		}
		$module = max( 2, (int) $module );
		$html   = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:#ffffff;border:' . ( 4 * $module ) . 'px solid #ffffff;">';
		foreach ( $grid as $row ) {
			$html .= '<tr>';
			foreach ( $row as $dark ) {
				$html .= '<td width="' . $module . '" height="' . $module . '" style="width:' . $module . 'px;height:' . $module . 'px;padding:0;background:' . ( $dark ? '#000000' : '#ffffff' ) . ';"></td>';
			}
			$html .= '</tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Byte mode data with terminator and padding.
	 *
	 * @param int[] $bytes    Input bytes.
	 * @param int   $capacity Data codewords.
	 * @return int[]
	 */
	private static function data_codewords( array $bytes, $capacity ) {
		$bits = '0100' . str_pad( decbin( count( $bytes ) ), 8, '0', STR_PAD_LEFT );
		foreach ( $bytes as $byte ) {
			$bits .= str_pad( decbin( $byte ), 8, '0', STR_PAD_LEFT );
		}
		$bits   .= str_repeat( '0', min( 4, $capacity * 8 - strlen( $bits ) ) );
		$bits   .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
		$out     = array_map( 'bindec', str_split( $bits, 8 ) );
		$missing = $capacity - count( $out );
		for ( $pad = 0; $pad < $missing; $pad++ ) {
			$out[] = 0 === $pad % 2 ? 0xEC : 0x11;
		}
		return $out;
	}

	/**
	 * Split into blocks, add Reed-Solomon codewords and interleave.
	 *
	 * @param int[] $data      Data codewords.
	 * @param int   $per_block Data codewords per block.
	 * @param int   $blocks    Number of blocks.
	 * @param int   $ec_len    EC codewords per block.
	 * @return int[]
	 */
	private static function interleave( array $data, $per_block, $blocks, $ec_len ) {
		$data_blocks = array_chunk( $data, $per_block );
		$ec_blocks   = array();
		foreach ( $data_blocks as $block ) {
			$ec_blocks[] = self::reed_solomon( $block, $ec_len );
		}
		$out = array();
		for ( $i = 0; $i < $per_block; $i++ ) {
			for ( $b = 0; $b < $blocks; $b++ ) {
				$out[] = $data_blocks[ $b ][ $i ];
			}
		}
		for ( $i = 0; $i < $ec_len; $i++ ) {
			for ( $b = 0; $b < $blocks; $b++ ) {
				$out[] = $ec_blocks[ $b ][ $i ];
			}
		}
		return $out;
	}

	/**
	 * Reed-Solomon error correction codewords over GF(256).
	 *
	 * @param int[] $data   Data codewords.
	 * @param int   $ec_len Number of EC codewords.
	 * @return int[]
	 */
	private static function reed_solomon( array $data, $ec_len ) {
		self::tables();
		$gen = array( 1 );
		for ( $i = 0; $i < $ec_len; $i++ ) {
			$next = array_fill( 0, count( $gen ) + 1, 0 );
			foreach ( $gen as $j => $coef ) {
				$next[ $j ]     ^= $coef;
				$next[ $j + 1 ] ^= self::mul( $coef, self::$exp[ $i ] );
			}
			$gen = $next;
		}
		$rem = array_fill( 0, $ec_len, 0 );
		foreach ( $data as $byte ) {
			$factor = $byte ^ array_shift( $rem );
			$rem[]  = 0;
			for ( $j = 0; $j < $ec_len; $j++ ) {
				$rem[ $j ] ^= self::mul( $gen[ $j + 1 ], $factor );
			}
		}
		return $rem;
	}

	/**
	 * Build GF(256) tables once.
	 */
	private static function tables() {
		if ( self::$exp ) {
			return;
		}
		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$exp[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x             <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11D;
			}
		}
	}

	/**
	 * Multiply in GF(256).
	 *
	 * @param int $a Factor.
	 * @param int $b Factor.
	 * @return int
	 */
	private static function mul( $a, $b ) {
		if ( 0 === $a || 0 === $b ) {
			return 0;
		}
		return self::$exp[ ( self::$log[ $a ] + self::$log[ $b ] ) % 255 ];
	}

	/**
	 * Finder, separator, timing and alignment patterns, the dark module and
	 * the reserved format areas.
	 *
	 * @param int $size  Matrix size.
	 * @param int $align Alignment pattern centre, 0 for none.
	 * @return array [grid, reserved]
	 */
	private static function function_patterns( $size, $align ) {
		$grid     = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$set      = static function ( $x, $y, $dark ) use ( &$grid, &$reserved, $size ) {
			if ( $x >= 0 && $y >= 0 && $x < $size && $y < $size ) {
				$grid[ $y ][ $x ]     = $dark;
				$reserved[ $y ][ $x ] = true;
			}
		};
		foreach ( array( array( 0, 0 ), array( $size - 7, 0 ), array( 0, $size - 7 ) ) as $corner ) {
			for ( $dy = -1; $dy <= 7; $dy++ ) {
				for ( $dx = -1; $dx <= 7; $dx++ ) {
					$ring = max( abs( $dx - 3 ), abs( $dy - 3 ) );
					$set( $corner[0] + $dx, $corner[1] + $dy, 2 !== $ring && $ring <= 3 );
				}
			}
		}
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$set( $i, 6, 0 === $i % 2 );
			$set( 6, $i, 0 === $i % 2 );
		}
		if ( $align ) {
			for ( $dy = -2; $dy <= 2; $dy++ ) {
				for ( $dx = -2; $dx <= 2; $dx++ ) {
					$ring = max( abs( $dx ), abs( $dy ) );
					$set( $align + $dx, $align + $dy, 1 !== $ring );
				}
			}
		}
		$set( 8, $size - 8, true );
		for ( $i = 0; $i < 9; $i++ ) {
			if ( ! $reserved[8][ $i ] ) {
				$reserved[8][ $i ] = true;
			}
			if ( ! $reserved[ $i ][8] ) {
				$reserved[ $i ][8] = true;
			}
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$reserved[8][ $size - 1 - $i ] = true;
			$reserved[ $size - 1 - $i ][8] = true;
		}
		return array( $grid, $reserved );
	}

	/**
	 * Place codewords in the zigzag order, masked.
	 *
	 * @param array $grid      Matrix.
	 * @param array $reserved  Function pattern map.
	 * @param int[] $codewords Codewords.
	 * @param int   $mask      Mask pattern 0 to 7.
	 * @param int   $size      Matrix size.
	 */
	private static function place_data( array &$grid, array $reserved, array $codewords, $mask, $size ) {
		$bits = '';
		foreach ( $codewords as $codeword ) {
			$bits .= str_pad( decbin( $codeword ), 8, '0', STR_PAD_LEFT );
		}
		$index  = 0;
		$total  = strlen( $bits );
		$upward = true;
		for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $step = 0; $step < $size; $step++ ) {
				$y = $upward ? $size - 1 - $step : $step;
				for ( $col = 0; $col < 2; $col++ ) {
					$x = $right - $col;
					if ( $reserved[ $y ][ $x ] ) {
						continue;
					}
					$dark             = $index < $total && '1' === $bits[ $index ];
					$flip             = self::mask_bit( $mask, $x, $y );
					$grid[ $y ][ $x ] = $dark ? ! $flip : $flip;
					++$index;
				}
			}
			$upward = ! $upward;
		}
	}

	/**
	 * Whether a mask flips the module at x, y.
	 *
	 * @param int $mask Mask pattern.
	 * @param int $x    Column.
	 * @param int $y    Row.
	 * @return bool
	 */
	private static function mask_bit( $mask, $x, $y ) {
		switch ( $mask ) {
			case 0:
				return 0 === ( $x + $y ) % 2;
			case 1:
				return 0 === $y % 2;
			case 2:
				return 0 === $x % 3;
			case 3:
				return 0 === ( $x + $y ) % 3;
			case 4:
				return 0 === ( intdiv( $y, 2 ) + intdiv( $x, 3 ) ) % 2;
			case 5:
				return 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3;
			case 6:
				return 0 === ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2;
			default:
				return 0 === ( ( $x + $y ) % 2 + ( $x * $y ) % 3 ) % 2;
		}
	}

	/**
	 * Write the format information (level M and mask) in both copies.
	 *
	 * @param array $grid Matrix.
	 * @param int   $mask Mask pattern.
	 * @param int   $size Matrix size.
	 */
	private static function place_format( array &$grid, $mask, $size ) {
		$data = $mask;
		$rem  = $data << 10;
		for ( $i = 14; $i >= 10; $i-- ) {
			if ( $rem & ( 1 << $i ) ) {
				$rem ^= 0x537 << ( $i - 10 );
			}
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$bit  = static function ( $i ) use ( $bits ) {
			return (bool) ( ( $bits >> $i ) & 1 );
		};
		for ( $i = 0; $i <= 5; $i++ ) {
			$grid[ $i ][8] = $bit( $i );
		}
		$grid[7][8] = $bit( 6 );
		$grid[8][8] = $bit( 7 );
		$grid[8][7] = $bit( 8 );
		for ( $i = 9; $i < 15; $i++ ) {
			$grid[8][ 14 - $i ] = $bit( $i );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$grid[8][ $size - 1 - $i ] = $bit( $i );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$grid[ $size - 15 + $i ][8] = $bit( $i );
		}
		$grid[ $size - 8 ][8] = true;
	}

	/**
	 * Penalty score used to pick the clearest mask.
	 *
	 * @param array $grid Matrix.
	 * @param int   $size Matrix size.
	 * @return int
	 */
	private static function penalty( array $grid, $size ) {
		$score = 0;
		$dark  = 0;
		for ( $a = 0; $a < $size; $a++ ) {
			foreach ( array( true, false ) as $rows ) {
				$run  = 1;
				$line = '';
				for ( $b = 0; $b < $size; $b++ ) {
					$cell  = $rows ? $grid[ $a ][ $b ] : $grid[ $b ][ $a ];
					$line .= $cell ? '1' : '0';
					if ( $b > 0 ) {
						$prev = $rows ? $grid[ $a ][ $b - 1 ] : $grid[ $b - 1 ][ $a ];
						if ( $prev === $cell ) {
							++$run;
						} else {
							$score += $run >= 5 ? $run - 2 : 0;
							$run    = 1;
						}
					}
				}
				$score += $run >= 5 ? $run - 2 : 0;
				$score += 40 * ( substr_count( $line, '10111010000' ) + substr_count( $line, '00001011101' ) );
			}
			for ( $b = 0; $b < $size; $b++ ) {
				$dark += $grid[ $a ][ $b ] ? 1 : 0;
				if ( $a < $size - 1 && $b < $size - 1 ) {
					$c = $grid[ $a ][ $b ];
					if ( $c === $grid[ $a + 1 ][ $b ] && $c === $grid[ $a ][ $b + 1 ] && $c === $grid[ $a + 1 ][ $b + 1 ] ) {
						$score += 3;
					}
				}
			}
		}
		$score += 10 * intdiv( abs( $dark * 20 - $size * $size * 10 ), $size * $size );
		return $score;
	}
}
