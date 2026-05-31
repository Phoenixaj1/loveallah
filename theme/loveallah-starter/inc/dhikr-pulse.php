<?php
/**
 * Dhikr Pulse — heart-rate-entrainment metronome.
 *
 * Starts at the user's resting BPM (80 default), exponentially decays
 * toward 40 BPM over ~5 min following bpm(t) = 40 + 40 * e^(-t/90).
 * The body entrains to the visual pulse; the mind follows. Tempo
 * entrainment via the inferior olive nucleus + slow-breathing vagal
 * tone = parasympathetic state → flow.
 *
 * Phrase-aware starting BPM (longer phrases need lower BPM):
 *   subhanallah / alhamdulillah / allahu akbar (4-5 syll) → 80 → 40
 *   la ilaha illa Allah (7 syll)                          → 60 → 30
 *   salawat (10+ syll)                                    → 40 → 20
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Wave 95: load shared scene library so Pulse can offer the same
// ambient backdrops as Solitude (ocean, forest, cosmos, nasheeds, etc.).
require_once get_template_directory() . '/inc/dhikr-scenes-data.php';

// Phrases the user can pick. start/end BPM tuned for syllable count.
$la_pulse_phrases = [
	[
		'key'      => 'subhanallah',
		'arabic'   => 'سُبْحَانَ ٱللَّٰه',
		'translit' => 'Subḥān Allāh',
		'meaning'  => 'Glory be to Allah',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'alhamdulillah',
		'arabic'   => 'ٱلْحَمْدُ لِلَّٰه',
		'translit' => 'Alḥamdulillāh',
		'meaning'  => 'Praise be to Allah',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'allahuakbar',
		'arabic'   => 'ٱللَّٰهُ أَكْبَر',
		'translit' => 'Allāhu Akbar',
		'meaning'  => 'Allah is the Greatest',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'kalimah',
		'arabic'   => 'لَا إِلٰهَ إِلَّا ٱللَّٰه',
		'translit' => 'Lā ilāha illa-llāh',
		'meaning'  => 'There is no god but Allah',
		'startBpm' => 60, 'endBpm' => 30,
	],
	[
		'key'      => 'salawat',
		'arabic'   => 'ٱللَّٰهُمَّ صَلِّ عَلَىٰ مُحَمَّد',
		'translit' => 'Allāhumma ṣalli ʿalā Muḥammad',
		'meaning'  => 'O Allah, send blessings upon Muhammad ﷺ',
		'startBpm' => 40, 'endBpm' => 20,
	],
];
?>
<main class="la-app la-app--pulse">

	<?php // Wave 95b: mode switcher replaces the back arrow. Tapping
	// "Solitude" returns to the default dhikr screen, so the standalone
	// back affordance was redundant. ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Solitude</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>" aria-current="page">Pulse</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<!-- ─── SETUP screen: pick phrase + count target ─── -->
	<section class="la-pulse-setup" data-pulse-scene="setup">
		<header class="la-pulse-setup-head">
			<div class="la-pulse-eyebrow">Pulse</div>
			<h1 class="la-pulse-title">Let the beat take you</h1>
			<p class="la-pulse-sub">Starts at your resting rate · slowly settles to a meditative 40 BPM</p>
		</header>

		<div class="la-pulse-pickrow">
			<div class="la-pulse-pickrow-label">Phrase</div>
			<div class="la-pulse-phrase-pills" data-pulse-phrase-pills>
				<?php foreach ( $la_pulse_phrases as $i => $p ) : ?>
					<button type="button"
						class="la-pulse-phrase-pill <?php echo $i === 0 ? 'is-active' : ''; ?>"
						data-pulse-phrase="<?php echo esc_attr( $p['key'] ); ?>"
						data-start-bpm="<?php echo (int) $p['startBpm']; ?>"
						data-end-bpm="<?php echo (int) $p['endBpm']; ?>">
						<span class="la-pulse-phrase-pill-translit"><?php echo esc_html( $p['translit'] ); ?></span>
						<span class="la-pulse-phrase-pill-bpm"><?php echo (int) $p['startBpm']; ?> → <?php echo (int) $p['endBpm']; ?> BPM</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="la-pulse-pickrow">
			<div class="la-pulse-pickrow-label">Count</div>
			<div class="la-pulse-count-pills" data-pulse-count-pills>
				<button type="button" class="la-pulse-count-pill is-active" data-pulse-count="33">33</button>
				<button type="button" class="la-pulse-count-pill" data-pulse-count="100">100</button>
				<button type="button" class="la-pulse-count-pill" data-pulse-count="300">300</button>
			</div>
		</div>

		<div class="la-pulse-begin-bar">
			<button type="button" class="la-pulse-begin" data-pulse-begin>
				<span class="la-pulse-begin-label">Begin</span>
				<span class="la-pulse-begin-meta" data-pulse-begin-meta>33 × Subḥān Allāh</span>
			</button>
		</div>
	</section>

	<!-- ─── SESSION screen: pulse + counter ─── -->
	<section class="la-pulse-session" data-pulse-scene="session" hidden>
		<!-- Wave 63: blossoming-heart backdrop. The image lives at
		     /assets/img/pulse-heart-bg.jpg. CSS layers a radial
		     vignette on top so the pulse rings + arabic stay clear. -->
		<div class="la-pulse-bg-image" aria-hidden="true"></div>
		<div class="la-pulse-bg-vignette" aria-hidden="true"></div>
		<!-- Wave 95: scene backdrop layer (full-bleed YT iframe). Sits
		     ABOVE the heart-bg image but BELOW the pulse stage. JS
		     injects the iframe only when the user picks a non-silent
		     scene, so quiet sessions stay quiet. -->
		<div class="la-pulse-bg-yt" data-pulse-bg-yt aria-hidden="true"></div>

		<!-- Background pulse rings (3 expanding circles, staggered) -->
		<div class="la-pulse-stage">
			<div class="la-pulse-ring la-pulse-ring--3" data-pulse-ring="3"></div>
			<div class="la-pulse-ring la-pulse-ring--2" data-pulse-ring="2"></div>
			<div class="la-pulse-ring la-pulse-ring--1" data-pulse-ring="1"></div>
			<div class="la-pulse-core" data-pulse-core>
				<div class="la-pulse-arabic" dir="rtl" lang="ar" data-pulse-arabic>سُبْحَانَ ٱللَّٰه</div>
				<div class="la-pulse-translit" data-pulse-translit>Subḥān Allāh</div>
			</div>
		</div>

		<!-- Bottom HUD: count + BPM display + controls -->
		<div class="la-pulse-hud">
			<div class="la-pulse-counter">
				<span class="la-pulse-counter-num" data-pulse-count-display>0</span>
				<span class="la-pulse-counter-sep">/</span>
				<span class="la-pulse-counter-target" data-pulse-target-display>33</span>
			</div>
			<div class="la-pulse-bpm">
				<span class="la-pulse-bpm-num" data-pulse-bpm-display>80</span>
				<span class="la-pulse-bpm-unit">BPM</span>
			</div>
			<div class="la-pulse-controls">
				<!-- Haptic toggle. JS flips .is-active to reflect the saved
				     preference; an opt-in confirmation buzz fires on enable
				     so the user feels what's about to happen each beat. -->
				<button type="button" class="la-pulse-ctrl la-pulse-ctrl--haptic" data-pulse-haptic aria-label="Toggle haptic feedback" title="Toggle haptic">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
						<rect x="7" y="2.5" width="10" height="19" rx="2"/>
						<line x1="3" y1="9" x2="3" y2="15"/>
						<line x1="21" y1="9" x2="21" y2="15"/>
					</svg>
				</button>
				<!-- Wave 95: scene picker. Tap → bottom-sheet with the
				     full scene library (ocean / forest / cosmos / nasheeds
				     etc). Same data + UX as Solitude. The current scene
				     emoji renders inside so the user sees what's active. -->
				<button type="button" class="la-pulse-ctrl la-pulse-ctrl--scene" data-pulse-scenes-open aria-label="Change scene" title="Change scene">
					<span data-pulse-scene-emoji aria-hidden="true">💗</span>
				</button>
				<!-- Wave 95: mute toggle. Off by default — picking a noisy
				     scene then muting gives the user the visuals without
				     the audio. Sends YT IFrame postMessage commands to
				     mute/unmute the embedded video. -->
				<button type="button" class="la-pulse-ctrl la-pulse-ctrl--mute" data-pulse-mute aria-label="Mute audio" title="Mute audio">
					<svg class="la-pulse-mute-on" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
					<svg class="la-pulse-mute-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
				</button>
				<button type="button" class="la-pulse-ctrl" data-pulse-hold aria-label="Hold this pace" title="Hold this pace">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
				</button>
				<button type="button" class="la-pulse-ctrl" data-pulse-deepen aria-label="Go deeper" title="Go deeper">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
				</button>
				<button type="button" class="la-pulse-ctrl la-pulse-ctrl--end" data-pulse-end aria-label="End session" title="End session">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>
			</div>
		</div>

		<!-- Wave 95: scene library bottom-sheet. Same shape as Solitude's,
		     grouped by audio-type genre so the user picks calm-without-
		     music or nasheed-with-vocals in one tap. -->
		<div class="la-pulse-scenes" data-pulse-scenes hidden role="dialog" aria-modal="true" aria-label="Background scenes">
			<button type="button" class="la-pulse-scenes-scrim" data-pulse-scenes-close aria-label="Close"></button>
			<div class="la-pulse-scenes-panel">
				<div class="la-pulse-scenes-grab" aria-hidden="true"></div>
				<header class="la-pulse-scenes-head">
					<h3>Choose a scene</h3>
					<button type="button" class="la-pulse-scenes-x" data-pulse-scenes-close aria-label="Close">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</header>
				<div class="la-pulse-scenes-body" role="radiogroup" aria-label="Background scene">
					<?php
					$grouped = [];
					foreach ( $la_scenes as $s ) {
						$g = $s['genre'] ?? 'nature';
						$grouped[ $g ][] = $s;
					}
					foreach ( [ 'nature', 'silence', 'ambient', 'nasheed' ] as $g ) :
						if ( empty( $grouped[ $g ] ) ) continue;
						$meta = $la_scene_genres[ $g ] ?? [];
					?>
						<section class="la-pulse-scenes-section">
							<header class="la-pulse-scenes-section-head">
								<div class="la-pulse-scenes-section-title"><?php echo esc_html( $meta['label'] ?? '' ); ?></div>
								<div class="la-pulse-scenes-section-sub"><?php echo esc_html( $meta['sub'] ?? '' ); ?></div>
							</header>
							<?php foreach ( $grouped[ $g ] as $s ) : ?>
								<button type="button"
									class="la-pulse-scenes-item"
									role="radio"
									aria-checked="false"
									data-pulse-scene-key="<?php echo esc_attr( $s['key'] ); ?>"
									data-pulse-scene-video="<?php echo esc_attr( $s['video'] ?? '' ); ?>"
									data-pulse-scene-emoji="<?php echo esc_attr( $s['emoji'] ); ?>">
									<span class="la-pulse-scenes-item-emoji" aria-hidden="true"><?php echo $s['emoji']; ?></span>
									<span class="la-pulse-scenes-item-text">
										<span class="la-pulse-scenes-item-label"><?php echo esc_html( $s['label'] ); ?></span>
										<span class="la-pulse-scenes-item-desc"><?php echo esc_html( $s['desc'] ); ?></span>
									</span>
									<span class="la-pulse-scenes-item-check" aria-hidden="true">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
									</span>
								</button>
							<?php endforeach; ?>
						</section>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ─── COMPLETE screen ─── -->
	<section class="la-pulse-complete" data-pulse-scene="complete" hidden>
		<div class="la-pulse-complete-inner">
			<div class="la-pulse-complete-arabic" dir="rtl" lang="ar">سُبْحَانَ ٱللَّٰه</div>
			<div class="la-pulse-complete-line">The heart has remembered.</div>
			<div class="la-pulse-complete-actions">
				<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-pulse-secondary">Return</a>
				<button type="button" class="la-pulse-primary" data-pulse-again>Again</button>
			</div>
		</div>
	</section>

	<script id="la-pulse-config" type="application/json">
		<?php echo wp_json_encode( $la_pulse_phrases ); ?>
	</script>

	<script>
	/* Wave 95: scene picker + mute for Pulse.
	   Self-contained — doesn't depend on the main loveallah.js scene
	   logic (which is solitude-class-specific). Lives inline so the
	   Pulse template owns its session controls end-to-end. */
	(function() {
		const root      = document.querySelector('.la-app--pulse');
		if (!root) return;
		const ytHost    = root.querySelector('[data-pulse-bg-yt]');
		const sceneBtn  = root.querySelector('[data-pulse-scenes-open]');
		const sheet     = root.querySelector('[data-pulse-scenes]');
		const muteBtn   = root.querySelector('[data-pulse-mute]');
		const muteOn    = root.querySelector('.la-pulse-mute-on');
		const muteOff   = root.querySelector('.la-pulse-mute-off');
		const emojiEl   = root.querySelector('[data-pulse-scene-emoji]');
		const closeBtns = root.querySelectorAll('[data-pulse-scenes-close]');
		const items     = root.querySelectorAll('[data-pulse-scene-key]');
		if (!ytHost) return;

		let isMuted = false;  // default: audio ON (user picks scene → hears it)
		let currentVideo = '';

		function applyIframeSrc(videoId) {
			ytHost.innerHTML = '';
			const session = root.querySelector('.la-pulse-session');
			if (!videoId) {
				currentVideo = '';
				session?.classList.remove('has-scene');
				return;
			}
			session?.classList.add('has-scene');
			const muteParam = isMuted ? '1' : '0';
			const url = `https://www.youtube-nocookie.com/embed/${videoId}`
				+ `?autoplay=1&mute=${muteParam}&loop=1&playlist=${videoId}`
				+ `&controls=0&modestbranding=1&playsinline=1&rel=0`
				+ `&iv_load_policy=3&cc_load_policy=0&disablekb=1&fs=0&enablejsapi=1`;
			const f = document.createElement('iframe');
			f.src = url;
			f.allow = 'autoplay; encrypted-media';
			f.frameBorder = '0';
			f.setAttribute('aria-hidden', 'true');
			ytHost.appendChild(f);
			currentVideo = videoId;
		}

		function postYt(func, args) {
			const iframe = ytHost.querySelector('iframe');
			if (!iframe || !iframe.contentWindow) return;
			try {
				iframe.contentWindow.postMessage(JSON.stringify({
					event: 'command', func, args: args || []
				}), '*');
			} catch (_) {}
		}

		function setMute(on) {
			isMuted = !!on;
			muteBtn?.classList.toggle('is-muted', isMuted);
			if (muteOn)  muteOn.style.display  = isMuted ? 'none' : '';
			if (muteOff) muteOff.style.display = isMuted ? '' : 'none';
			postYt(isMuted ? 'mute' : 'unMute');
		}

		// Wire scene-picker open/close
		sceneBtn?.addEventListener('click', () => {
			sheet?.removeAttribute('hidden');
		});
		closeBtns.forEach(b => b.addEventListener('click', () => sheet?.setAttribute('hidden', '')));

		// Wire scene selection
		items.forEach(item => {
			item.addEventListener('click', () => {
				const vid    = item.dataset.pulseSceneVideo || '';
				const emoji  = item.dataset.pulseSceneEmoji || '🌙';
				items.forEach(i => { i.classList.remove('is-selected'); i.setAttribute('aria-checked', 'false'); });
				item.classList.add('is-selected');
				item.setAttribute('aria-checked', 'true');
				if (emojiEl) emojiEl.textContent = emoji;
				applyIframeSrc(vid);
				sheet?.setAttribute('hidden', '');
			});
		});

		// Wire mute toggle
		muteBtn?.addEventListener('click', () => setMute(!isMuted));
	})();
	</script>
</main>
