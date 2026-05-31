<?php
/**
 * Dhikr Pray — guided Sunnah dhikr after prayer (Wave 106).
 *
 * Walks the user through the established sequence done after each
 * obligatory prayer (Tasbih Fatima + completing dhikr + protective
 * recitations):
 *
 *   1. Astaghfirullah                 ×3
 *   2. Allahumma anta as-Salām...     ×1   (peace dua)
 *   3. Subḥān Allāh                   ×33
 *   4. Al-ḥamdu lillāh                ×33
 *   5. Allāhu Akbar                   ×33
 *   6. Lā ilāha illa-llāh waḥdahu... ×1   (the completion, the 100th)
 *   7. Āyat al-Kursī                  ×1   (recite)
 *   8. Al-Ikhlāṣ · Al-Falaq · An-Nās ×1   (recite — after Fajr/Maghrib ×3)
 *
 * UX:
 *   - Big Arabic + transliteration + meaning + step indicator
 *   - Tap anywhere on the orb to count
 *   - Auto-advance to next step when count reaches target
 *   - Step dots at top show position in sequence
 *   - Right rail: restart + previous + next + (mute if scene added later)
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once get_template_directory() . '/inc/dhikr-live-scenes.php';

// Steps in order. n = target count; recite=true means "recite the
// passage" rather than count taps (auto-advances after a single tap).
$la_pray_steps = [
	[
		'key'      => 'astaghfirullah',
		'arabic'   => 'أَسْتَغْفِرُ ٱللَّٰه',
		'translit' => 'Astaghfirullāh',
		'meaning'  => 'I seek forgiveness of Allah',
		'note'     => 'Begin by seeking forgiveness — three times',
		'n'        => 3,
	],
	[
		'key'      => 'allahumma_salam',
		'arabic'   => 'اللَّهُمَّ أَنْتَ ٱلسَّلَامُ وَمِنْكَ ٱلسَّلَامُ تَبَارَكْتَ يَا ذَا ٱلْجَلَالِ وَٱلْإِكْرَام',
		'translit' => "Allāhumma anta as-Salām wa minka as-Salām, tabārakta yā Dhal-Jalāli wal-Ikrām",
		'meaning'  => 'O Allah, You are Peace and from You comes peace. Blessed are You, O Possessor of Majesty and Honour',
		'note'     => 'The greeting of peace — once',
		'n'        => 1,
		'recite'   => true,
	],
	[
		'key'      => 'subhanallah',
		'arabic'   => 'سُبْحَانَ ٱللَّٰه',
		'translit' => 'Subḥān Allāh',
		'meaning'  => 'Glory be to Allah',
		'note'     => 'Glorification — thirty-three times',
		'n'        => 33,
	],
	[
		'key'      => 'alhamdulillah',
		'arabic'   => 'ٱلْحَمْدُ لِلَّٰه',
		'translit' => 'Al-ḥamdu lillāh',
		'meaning'  => 'All praise is for Allah',
		'note'     => 'Gratitude — thirty-three times',
		'n'        => 33,
	],
	[
		'key'      => 'allahuakbar',
		'arabic'   => 'ٱللَّٰهُ أَكْبَر',
		'translit' => 'Allāhu Akbar',
		'meaning'  => 'Allah is the Greatest',
		'note'     => 'Magnification — thirty-three times',
		'n'        => 33,
	],
	[
		'key'      => 'tahleel',
		'arabic'   => 'لَا إِلَٰهَ إِلَّا ٱللَّٰهُ وَحْدَهُ لَا شَرِيكَ لَهُ، لَهُ ٱلْمُلْكُ وَلَهُ ٱلْحَمْدُ وَهُوَ عَلَىٰ كُلِّ شَيْءٍ قَدِير',
		'translit' => "Lā ilāha illa-llāh waḥdahu lā sharīka lah, lahul-mulku wa lahul-ḥamd, wa huwa ʿalā kulli shayʾin qadīr",
		'meaning'  => 'There is no god but Allah alone, with no partner. His is the dominion and His is all praise, and He has power over everything',
		'note'     => 'The completion — once, sealing the hundred',
		'n'        => 1,
		'recite'   => true,
	],
	[
		'key'      => 'kursi',
		'arabic'   => 'آيَةُ ٱلْكُرْسِيّ',
		'translit' => 'Āyat al-Kursī',
		'meaning'  => 'Recite the Throne Verse (Qurʾan 2:255). Nothing prevents you from entering paradise after a fardh prayer except death.',
		'note'     => 'The Throne Verse — once after every obligatory prayer',
		'n'        => 1,
		'recite'   => true,
	],
	[
		'key'      => 'three_suras',
		'arabic'   => 'ٱلْإِخْلَاص · ٱلْفَلَق · ٱلنَّاس',
		'translit' => 'Al-Ikhlāṣ · Al-Falaq · An-Nās',
		'meaning'  => 'Recite the three protective Suras (Qurʾan 112, 113, 114). After Fajr and Maghrib recite each three times.',
		'note'     => 'The three protective Suras',
		'n'        => 1,
		'recite'   => true,
	],
];
?>
<main class="la-app la-app--pray la-dhikr-live mode-pray" data-dhikr-mode="pray">

	<?php // Wave 95b mode-switcher pills ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Breathe</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Focus</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=pray' ) ); ?>" aria-current="page">Pray</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<div class="pray-live" data-pray>

		<?php // Step progress dots — one per step in the sequence ?>
		<div class="pray-progress" data-pray-progress aria-label="Sequence progress">
			<?php foreach ( $la_pray_steps as $i => $s ) : ?>
				<span class="pray-dot <?php echo $i === 0 ? 'is-active' : ''; ?>" data-pray-dot="<?php echo (int) $i; ?>"></span>
			<?php endforeach; ?>
		</div>

		<?php // Centre — current step ?>
		<div class="pray-stage">
			<div class="pray-eyebrow" data-pray-note><?php echo esc_html( $la_pray_steps[0]['note'] ); ?></div>
			<div class="pray-arabic ar" data-pray-ar dir="rtl" lang="ar"><?php echo esc_html( $la_pray_steps[0]['arabic'] ); ?></div>
			<div class="pray-translit" data-pray-tr><?php echo esc_html( $la_pray_steps[0]['translit'] ); ?></div>
			<div class="pray-meaning" data-pray-en><?php echo esc_html( $la_pray_steps[0]['meaning'] ); ?></div>
			<div class="pray-counter">
				<span class="pray-count" data-pray-count>0</span>
				<span class="pray-target" data-pray-target>/ <?php echo (int) $la_pray_steps[0]['n']; ?></span>
			</div>
			<div class="pray-hint" data-pray-hint>Tap anywhere to count</div>
		</div>

		<?php // Tap-anywhere overlay ?>
		<div class="pray-touch" data-pray-touch aria-hidden="true"></div>

		<?php // Right rail — Previous · Restart · Next ?>
		<div class="amb-rail" data-pray-rail>
			<button type="button" class="amb-rail-btn" data-pray-prev aria-label="Previous step">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
			</button>
			<button type="button" class="amb-rail-btn" data-pray-restart aria-label="Restart sequence">
				<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
			</button>
			<button type="button" class="amb-rail-btn amb-rail-btn--accent" data-pray-next aria-label="Next step">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
			</button>
		</div>

		<?php // Completion card — shown when the last step is done ?>
		<div class="pray-complete" data-pray-complete hidden>
			<div class="pray-complete-ar ar" dir="rtl" lang="ar">تَقَبَّلَ ٱللَّٰهُ مِنَّا وَمِنْكُمْ</div>
			<div class="pray-complete-tr">Taqabbal Allāhu minnā wa minkum</div>
			<div class="pray-complete-en">May Allah accept from us and from you</div>
			<button type="button" class="pray-restart-btn" data-pray-restart-big>Begin again</button>
		</div>
	</div>

	<script id="la-pray-config" type="application/json">
		<?php echo wp_json_encode( $la_pray_steps ); ?>
	</script>

	<script>
	/* Wave 106: PrayLive — guided sequence state machine.
	   State: step index + count. Tap to count. When count reaches
	   target, auto-advance after a brief pause (so the user feels
	   the completion). Recite steps (target = 1) advance on a
	   single tap. Last step → completion card. */
	(function() {
		const root = document.querySelector('[data-pray]');
		if ( ! root ) return;
		const STEPS = JSON.parse( document.getElementById('la-pray-config').textContent );

		let step  = 0;
		let count = 0;
		let done  = false;

		const stage     = root.querySelector('.pray-stage');
		const noteEl    = root.querySelector('[data-pray-note]');
		const arEl      = root.querySelector('[data-pray-ar]');
		const trEl      = root.querySelector('[data-pray-tr]');
		const enEl      = root.querySelector('[data-pray-en]');
		const countEl   = root.querySelector('[data-pray-count]');
		const targetEl  = root.querySelector('[data-pray-target]');
		const hintEl    = root.querySelector('[data-pray-hint]');
		const touch     = root.querySelector('[data-pray-touch]');
		const dots      = root.querySelectorAll('[data-pray-dot]');
		const prevBtn   = root.querySelector('[data-pray-prev]');
		const nextBtn   = root.querySelector('[data-pray-next]');
		const restartBtn = root.querySelector('[data-pray-restart]');
		const completeEl = root.querySelector('[data-pray-complete]');
		const restartBig = root.querySelector('[data-pray-restart-big]');

		function s() { return STEPS[step]; }

		function render() {
			if ( done ) {
				stage.hidden = true;
				touch.hidden = true;
				completeEl.hidden = false;
				dots.forEach( d => d.classList.add('is-active') );
				return;
			}
			stage.hidden = false;
			touch.hidden = false;
			completeEl.hidden = true;
			const cur = s();
			noteEl.textContent  = cur.note;
			arEl.textContent    = cur.arabic;
			trEl.textContent    = cur.translit;
			enEl.textContent    = cur.meaning;
			countEl.textContent = count;
			targetEl.textContent = '/ ' + cur.n;
			hintEl.textContent  = cur.recite ? 'Tap when you have recited' : 'Tap anywhere to count';
			dots.forEach( (d, i) => d.classList.toggle( 'is-active', i <= step ) );
			prevBtn.disabled = ( step === 0 );
			prevBtn.style.opacity = ( step === 0 ) ? '0.4' : '1';
		}

		function doCount() {
			if ( done ) return;
			const cur = s();
			count++;
			if ( navigator.vibrate ) navigator.vibrate(8);
			render();
			if ( count >= cur.n ) {
				/* Brief pause so the user sees the completion before
				   advancing — feels like a deliberate step-through, not
				   a hurried machine. */
				setTimeout( () => advanceStep(), 480 );
			}
		}

		function advanceStep() {
			if ( step + 1 >= STEPS.length ) {
				done = true;
				render();
				return;
			}
			step++;
			count = 0;
			render();
		}

		function backStep() {
			if ( step === 0 ) return;
			step--;
			count = 0;
			done = false;
			render();
		}

		function restart() {
			step  = 0;
			count = 0;
			done  = false;
			render();
		}

		touch.addEventListener('click', doCount);
		prevBtn.addEventListener('click', backStep);
		nextBtn.addEventListener('click', () => { count = 0; advanceStep(); });
		restartBtn.addEventListener('click', restart);
		restartBig?.addEventListener('click', restart);

		render();
	})();
	</script>
</main>
