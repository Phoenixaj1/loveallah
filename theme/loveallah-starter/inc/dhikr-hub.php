<?php
/**
 * Dhikr hub — 4-card chooser between modes.
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<main class="la-app la-app--dhikr-hub">
	<header class="la-dhikr-hub-head">
		<div class="la-dhikr-hub-eyebrow">Remembrance</div>
		<h1 class="la-dhikr-hub-title">Choose your way</h1>
		<p class="la-dhikr-hub-sub">Four paths to the same door — pick what your heart needs right now.</p>
	</header>

	<div class="la-dhikr-hub-grid">

		<!-- Solitude — current orb experience -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--solitude" href="<?php echo esc_url( home_url( '/dhikr/?mode=solitude' ) ); ?>">
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
					<circle cx="24" cy="24" r="14"/>
					<circle cx="24" cy="24" r="8" stroke-dasharray="2 3"/>
					<circle cx="24" cy="24" r="2.4" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Solitude</div>
			<div class="la-dhikr-hub-card-tag">Your breath, your count, your pace</div>
			<div class="la-dhikr-hub-card-desc">A quiet orb that breathes with you. Single phrase, sunnah count, total silence. The classical practice.</div>
		</a>

		<!-- Witness — feed of dhikr content + tap counter -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--witness" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
					<rect x="8" y="10" width="32" height="28" rx="4"/>
					<path d="M20 18l10 6-10 6z" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Witness</div>
			<div class="la-dhikr-hub-card-tag">Scholars chant — follow along</div>
			<div class="la-dhikr-hub-card-desc">A feed of qaris and shuyukh doing dhikr aloud. Tap the counter as you chant with them. Mirror what's in front of you.</div>
		</a>

		<!-- Pulse — BPM-driven flow state -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--pulse" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
					<circle cx="24" cy="24" r="5" fill="currentColor" stroke="none"/>
					<circle cx="24" cy="24" r="11" opacity="0.55"/>
					<circle cx="24" cy="24" r="17" opacity="0.25"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Pulse</div>
			<div class="la-dhikr-hub-card-tag">Heart entrains to a beat · 80 → 40 BPM</div>
			<div class="la-dhikr-hub-card-desc">A gentle rhythm that starts at your resting heart rate and slows you down. Body finds the cadence; mind follows.</div>
		</a>

		<!-- Names — 99 Names contemplation -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--names" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 32c0-10 6-16 12-16s12 6 12 16"/>
					<path d="M16 32c0-7 3.5-11 8-11s8 4 8 11"/>
					<circle cx="24" cy="36" r="2" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Names</div>
			<div class="la-dhikr-hub-card-tag">One name · one minute · closer through knowing</div>
			<div class="la-dhikr-hub-card-desc">Sit with His names. Pick 1, 3 or 7 — each one held in mind long enough that it changes how you see the day.</div>
		</a>

	</div>

	<p class="la-dhikr-hub-footnote">
		Any mode counts toward today's remembrance — pick what your heart needs.
	</p>
</main>
