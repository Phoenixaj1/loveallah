<?php
/**
 * Generates Play Store feature graphic (1024 x 500 PNG) for Love Allah.
 * Maghrib-sky gradient + brand wordmark + tagline.
 */

$w = 1024; $h = 500;
$im = imagecreatetruecolor($w, $h);

// Maghrib gradient #2C1338 → #6B1846 → #ED1C6C (vertical)
function lerp($a, $b, $t) { return (int)($a + ($b - $a) * $t); }
for ($y = 0; $y < $h; $y++) {
	$t = $y / $h;
	if ($t < 0.5) {
		$tt = $t * 2;
		$r = lerp(0x2C, 0x6B, $tt);
		$g = lerp(0x13, 0x18, $tt);
		$b = lerp(0x38, 0x46, $tt);
	} else {
		$tt = ($t - 0.5) * 2;
		$r = lerp(0x6B, 0xED, $tt);
		$g = lerp(0x18, 0x1C, $tt);
		$b = lerp(0x46, 0x6C, $tt);
	}
	$col = imagecolorallocate($im, $r, $g, $b);
	imageline($im, 0, $y, $w, $y, $col);
}

// Subtle stars
for ($i = 0; $i < 60; $i++) {
	$x = rand(0, $w - 1);
	$y = rand(0, (int)($h * 0.5));
	$opacity = rand(40, 100);
	$star = imagecolorallocatealpha($im, 255, 255, 255, 127 - (int)($opacity * 0.5));
	imagefilledellipse($im, $x, $y, 2, 2, $star);
}

// Crescent moon — a circle minus a slightly offset circle
$crescent_x = 200; $crescent_y = 250; $crescent_r = 120;
$gold   = imagecolorallocate($im, 0xF5, 0xE5, 0xB8);
$bg     = imagecolorallocatealpha($im, 0xED, 0x1C, 0x6C, 0); // approx ground color at that y
// Sample background color at the crescent y
$bg_rgb = imagecolorat($im, $crescent_x, $crescent_y);
$br = ($bg_rgb >> 16) & 0xFF;
$bgg = ($bg_rgb >> 8) & 0xFF;
$bb = $bg_rgb & 0xFF;
$bg_match = imagecolorallocate($im, $br, $bgg, $bb);
imagefilledellipse($im, $crescent_x, $crescent_y, $crescent_r * 2, $crescent_r * 2, $gold);
imagefilledellipse($im, $crescent_x + 40, $crescent_y - 10, $crescent_r * 2 - 30, $crescent_r * 2 - 30, $bg_match);

// White text
$white = imagecolorallocate($im, 255, 255, 255);
$white_dim = imagecolorallocatealpha($im, 255, 255, 255, 40);

// Use the largest built-in font (font 5 = 9x15) — multiplied via imagestring isn't bigger,
// but we can layer multiple to fake a big font. Better: load Inter or just use big font slot.
// PHP GD's only big text option without a TTF font is to draw a scaled bitmap.
// We'll write "Love Allah" via tiled large characters.
$title = 'Love Allah';
$tagline = 'A sacred space, in your pocket';
$cta = 'Prayer · Dhikr · Nasheeds · Reminders';

// Layer 1: title — repeat font 5 across with 3x manual scale
function bigtext($im, $text, $x, $y, $scale, $color) {
	$tmp_w = strlen($text) * 9;
	$tmp_h = 16;
	$tmp = imagecreatetruecolor($tmp_w, $tmp_h);
	$tbg = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
	imagealphablending($tmp, false);
	imagesavealpha($tmp, true);
	imagefilledrectangle($tmp, 0, 0, $tmp_w, $tmp_h, $tbg);
	$tcolor = imagecolorallocate($tmp, 255, 255, 255);
	imagestring($tmp, 5, 0, 0, $text, $tcolor);
	imagecopyresampled($im, $tmp, $x, $y, 0, 0, $tmp_w * $scale, $tmp_h * $scale, $tmp_w, $tmp_h);
	imagedestroy($tmp);
}

bigtext($im, $title,   430, 180, 5, $white);
bigtext($im, $tagline, 430, 290, 2, $white);
bigtext($im, $cta,     430, 360, 2, $white);

imagepng($im, __DIR__ . '/feature_graphic.png');
echo 'Wrote ' . __DIR__ . '/feature_graphic.png · ' . filesize(__DIR__ . '/feature_graphic.png') . ' bytes' . PHP_EOL;
imagedestroy($im);
