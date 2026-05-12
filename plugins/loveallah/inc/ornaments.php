<?php
/**
 * Inline SVG ornaments — Islamic geometric motifs.
 * Returns SVG strings to embed directly in templates.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Ornaments {

	/** Eight-pointed star (Rub el Hizb / Khatam) — for dividers + corners */
	public static function star( string $color = 'currentColor', int $size = 24 ) : string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . esc_attr( $color ) . '" aria-hidden="true">
			<path d="M12 1.5l2.5 5 5 2.5-5 2.5-2.5 5-2.5-5-5-2.5 5-2.5z" opacity=".4"/>
			<path d="M12 1.5l1.4 4 4.1 1-4.1 1 1.4 4-1.4 4 4.1-1-4.1 1-1.4 4-1.4-4-4.1 1 4.1-1-1.4-4 1.4-4-4.1-1 4.1-1z" transform="rotate(45 12 12)" opacity=".4"/>
		</svg>';
	}

	/** Refined 8-point star — cleaner, single shape */
	public static function khatam( int $size = 28 ) : string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" aria-hidden="true">
			<path d="M24 2 L29 12 L40 8 L36 19 L46 24 L36 29 L40 40 L29 36 L24 46 L19 36 L8 40 L12 29 L2 24 L12 19 L8 8 L19 12 Z"
				stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" fill="currentColor" fill-opacity="0.08"/>
		</svg>';
	}

	/** Section divider — diamond + lines + diamond */
	public static function divider() : string {
		return '<div class="la-divider" aria-hidden="true">
			<span class="la-divider-line"></span>
			<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
				<path d="M10 1 L19 10 L10 19 L1 10 Z" stroke="currentColor" stroke-width="1.2" fill="currentColor" fill-opacity="0.15"/>
				<circle cx="10" cy="10" r="2" fill="currentColor"/>
			</svg>
			<span class="la-divider-line"></span>
		</div>';
	}

	/** Mosque silhouette — for prayer card background */
	public static function mosque_silhouette() : string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 180" fill="none" aria-hidden="true" preserveAspectRatio="xMidYMax meet">
			<!-- Main dome -->
			<path d="M180 110 Q180 70 200 65 Q220 70 220 110 Z" fill="currentColor" fill-opacity="0.18"/>
			<!-- Crescent on main dome -->
			<circle cx="200" cy="56" r="2.5" fill="currentColor" fill-opacity="0.3"/>
			<line x1="200" y1="58" x2="200" y2="65" stroke="currentColor" stroke-width="0.8" stroke-opacity="0.3"/>
			<!-- Side domes -->
			<path d="M140 130 Q140 105 155 102 Q170 105 170 130 Z" fill="currentColor" fill-opacity="0.13"/>
			<path d="M230 130 Q230 105 245 102 Q260 105 260 130 Z" fill="currentColor" fill-opacity="0.13"/>
			<!-- Left minaret -->
			<rect x="95" y="60" width="6" height="100" fill="currentColor" fill-opacity="0.13"/>
			<path d="M93 60 L101 60 L102 55 L92 55 Z" fill="currentColor" fill-opacity="0.13"/>
			<path d="M95 55 Q98 45 101 55 Z" fill="currentColor" fill-opacity="0.15"/>
			<!-- Right minaret -->
			<rect x="299" y="60" width="6" height="100" fill="currentColor" fill-opacity="0.13"/>
			<path d="M297 60 L305 60 L306 55 L296 55 Z" fill="currentColor" fill-opacity="0.13"/>
			<path d="M299 55 Q302 45 305 55 Z" fill="currentColor" fill-opacity="0.15"/>
			<!-- Walls / facade -->
			<rect x="120" y="110" width="160" height="60" fill="currentColor" fill-opacity="0.15"/>
			<!-- Arched main entrance -->
			<path d="M188 170 L188 145 Q188 132 200 132 Q212 132 212 145 L212 170 Z" fill="currentColor" fill-opacity="0.22"/>
			<!-- Small arched windows -->
			<path d="M138 165 L138 152 Q138 145 144 145 Q150 145 150 152 L150 165 Z" fill="currentColor" fill-opacity="0.2"/>
			<path d="M250 165 L250 152 Q250 145 256 145 Q262 145 262 152 L262 165 Z" fill="currentColor" fill-opacity="0.2"/>
		</svg>';
	}

	/** Prayer-time icons — sun/moon by name */
	public static function prayer_icon( string $name, int $size = 16 ) : string {
		$paths = [
			'Fajr'    => '<circle cx="12" cy="14" r="3" fill="currentColor"/><line x1="12" y1="3" x2="12" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="4" y1="11" x2="7" y2="11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="17" y1="11" x2="20" y2="11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="6" y1="6" x2="8" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="18" y1="6" x2="16" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="17" x2="21" y2="17" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>',
			'Dhuhr'   => '<circle cx="12" cy="12" r="4" fill="currentColor"/><line x1="12" y1="2" x2="12" y2="5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="12" y1="19" x2="12" y2="22" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="2" y1="12" x2="5" y2="12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="19" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="5" y1="5" x2="7" y2="7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="17" y1="17" x2="19" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="5" y1="19" x2="7" y2="17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><line x1="17" y1="7" x2="19" y2="5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
			'Asr'     => '<circle cx="12" cy="12" r="3.5" fill="currentColor"/><line x1="12" y1="3" x2="12" y2="5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="12" x2="5" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="19" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="6" y1="18" x2="18" y2="18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity=".5"/>',
			'Maghrib' => '<path d="M5 17 a7 7 0 0 1 14 0" fill="currentColor"/><line x1="3" y1="20" x2="21" y2="20" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><line x1="12" y1="9" x2="12" y2="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="7" y1="11" x2="5.5" y2="9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="17" y1="11" x2="18.5" y2="9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
			'Isha'    => '<path d="M17 14 A6 6 0 1 1 11 8 a5 5 0 0 0 6 6z" fill="currentColor"/><circle cx="6" cy="6" r="0.8" fill="currentColor"/><circle cx="20" cy="9" r="0.8" fill="currentColor"/><circle cx="18" cy="18" r="0.8" fill="currentColor"/>',
		];
		$inner = $paths[ $name ] ?? '';
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
	}

	/** Tasbeeh bead — small decorative element */
	public static function tasbeeh_bead( int $size = 12 ) : string {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 12 12" aria-hidden="true">
			<circle cx="6" cy="6" r="4" fill="currentColor" fill-opacity="0.8"/>
			<circle cx="4.5" cy="4.5" r="1" fill="#fff" fill-opacity="0.5"/>
		</svg>';
	}

	/** Inline 8-point star tile — for body background pattern via CSS data URI */
	public static function pattern_data_uri( string $hex = '#ED1C6C', float $opacity = 0.04 ) : string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">
			<g fill="' . $hex . '" fill-opacity="' . $opacity . '">
				<path d="M40 8 L46 22 L60 28 L46 34 L40 48 L34 34 L20 28 L34 22 Z"/>
				<path d="M40 8 L46 22 L60 28 L46 34 L40 48 L34 34 L20 28 L34 22 Z" transform="rotate(45 40 28)"/>
				<path d="M0 68 L6 82 L20 88 L6 94 L0 108 L-6 94 L-20 88 L-6 82 Z" transform="translate(0,-20)"/>
				<path d="M80 68 L86 82 L100 88 L86 94 L80 108 L74 94 L60 88 L74 82 Z" transform="translate(0,-20)"/>
			</g>
		</svg>';
		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}
}
