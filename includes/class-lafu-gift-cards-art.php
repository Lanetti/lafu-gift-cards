<?php
/**
 * Card illustrations: flat vector art drawn on a 1586 x 1000 card. The art
 * stays in the top right, so the amount, code and QR code stay readable.
 *
 * @package Lafu_Gift_Cards
 */

defined( 'ABSPATH' ) || exit;

/**
 * Illustrations by design key. Each one is SVG markup without the outer
 * svg element; Lafu_Gift_Cards_Designs::art_layer() wraps and encodes it.
 */
class Lafu_Gift_Cards_Art {

	/**
	 * Every illustration.
	 *
	 * @return array Design key => SVG markup.
	 */
	public static function all() {
		return array(
			'christmas' => '<g transform="translate(1586 0) scale(.84) translate(-1586 0)"><g fill="#ffffff" fill-opacity=".16"><path d="M760 120l6 18 18 6-18 6-6 18-6-18-18-6 18-6z"/><path d="M980 420l5 15 15 5-15 5-5 15-5-15-15-5 15-5z"/><path d="M1560 80l5 15 15 5-15 5-5 15-5-15-15-5 15-5z"/></g><g stroke="#ffffff" stroke-opacity=".22" stroke-width="5" stroke-linecap="round"><path d="M900 180v50M876 192l48 26M876 218l48-26"/><path d="M1560 330v40M1540 340l40 20M1540 360l40-20"/><path d="M1060 560v36M1042 569l36 18M1042 587l36-18"/></g><rect x="1305" y="520" width="50" height="70" rx="8" fill="#6b3d25"/><path d="M1330 100l-150 190h300z" fill="#2f7a4f"/><path d="M1330 190l-190 220h380z" fill="#2f7a4f"/><path d="M1330 300l-220 240h440z" fill="#2f7a4f"/><path d="M1330 100l-150 190h150z" fill="#3f9461"/><path d="M1330 190l-190 220h190z" fill="#3f9461"/><path d="M1330 300l-220 240h220z" fill="#3f9461"/><g fill="none" stroke="#f3d27a" stroke-width="6" stroke-linecap="round"><path d="M1230 260q100 40 210-10"/><path d="M1190 380q140 50 290-14"/><path d="M1160 500q170 56 340-20"/></g><g><circle cx="1270" cy="240" r="17" fill="#ffffff"/><circle cx="1390" cy="270" r="17" fill="#e63946"/><circle cx="1220" cy="370" r="18" fill="#e63946"/><circle cx="1330" cy="400" r="18" fill="#f3d27a"/><circle cx="1450" cy="370" r="18" fill="#ffffff"/><circle cx="1180" cy="500" r="19" fill="#f3d27a"/><circle cx="1300" cy="516" r="19" fill="#ffffff"/><circle cx="1420" cy="508" r="19" fill="#e63946"/></g><path d="M1330 40l14 30 32 4-24 22 6 32-28-16-28 16 6-32-24-22 32-4z" fill="#f3d27a"/></g>',
			'ribbon'    => '<g transform="translate(-170 0)"><rect x="1170" y="-10" width="90" height="1020" fill="#d9ae52"/><rect x="1170" y="-10" width="18" height="1020" fill="#f0cf7e"/><rect x="1242" y="-10" width="18" height="1020" fill="#b98f38"/><path d="M1215 190C1120 70 960 80 980 170c18 80 150 60 235 20z" fill="#e2b85c"/><path d="M1215 190C1310 70 1470 80 1450 170c-18 80-150 60-235 20z" fill="#e2b85c"/><path d="M1215 190C1140 110 1030 110 1035 160c6 46 100 44 180 30z" fill="#c99a3e"/><path d="M1215 190C1290 110 1400 110 1395 160c-6 46-100 44-180 30z" fill="#c99a3e"/><path d="M1200 200l-90 190 46-10 30 44 50-210z" fill="#d9ae52"/><path d="M1230 200l90 190-46-10-30 44-50-210z" fill="#c99a3e"/><rect x="1180" y="150" width="70" height="84" rx="22" fill="#f0cf7e"/><rect x="1192" y="162" width="46" height="60" rx="16" fill="#e2b85c"/></g>',
		);
	}
}
