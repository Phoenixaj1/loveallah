<?php
/**
 * Dhikr Witness — WitnessReel full-bleed karaoke (Wave 99).
 *
 * Ported from Claude Design's WitnessReel component. Replaces the
 * snap-feed of vertical cards with a single full-bleed YouTube
 * portrait reel:
 *
 *   • Landscape (16:9) YouTube videos cover-cropped to portrait via
 *     iframe sized 1556×874 + transform: translateX(-50%). Iframe is
 *     pointer-events: none; a transparent touch layer above handles
 *     all input.
 *   • Tap on the layer → play/pause (sends postMessage to the player)
 *   • Vertical swipe up/down → previous/next recitation (cycles)
 *   • Right rail:
 *       Sound — starts muted (YouTube autoplay requires it); tap to
 *               unmute (counts as a user gesture).
 *       Videos — opens a bottom sheet with thumbnails (mqdefault.jpg
 *                from i.ytimg.com) to pick any video in the list.
 *   • Bottom caption: title / by / tag
 *   • Right-side pips show current position in the list
 *
 * Source data: the `dhikr_videos` table (curated dhikr recitations
 * verified by hand). Falls back to a placeholder list if empty.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$rows = $wpdb->get_results(
	"SELECT * FROM {$t['dhikr_videos']} ORDER BY sort_order ASC, id ASC LIMIT 30"
);
$la_wit_videos = [];
foreach ( $rows as $v ) {
	$la_wit_videos[] = [
		'id'    => $v->youtube_id,
		'title' => $v->title ?: 'Lā ilāha illa-llāh',
		'by'    => $v->scholar_name ?: ( $v->channel_handle ?: 'Dhikr' ),
		'tag'   => 'Lyrics',
	];
}
// Fallback so the page always renders something useful even if the
// dhikr_videos table is empty in a fresh install.
if ( empty( $la_wit_videos ) ) {
	$la_wit_videos = [
		[ 'id' => 'r4YrbaVbqPk', 'title' => 'Astaghfirullāh',     'by' => 'Mevlan Kurtishi', 'tag' => 'Nasheed' ],
		[ 'id' => 'maHPe1byTfk', 'title' => 'Ṣalawāt on the Prophet ﷺ', 'by' => 'Omar Hisham', 'tag' => 'Salawat' ],
		[ 'id' => 'n9oLl0HjV3Y', 'title' => 'Allāhu Akbar',        'by' => 'Adam Islamic Animation', 'tag' => 'Takbir' ],
		[ 'id' => 'aeeVsvAa0H8', 'title' => 'Subḥān Allāh wa biḥamdih', 'by' => 'Omar Hisham', 'tag' => 'Tasbeeh' ],
		[ 'id' => 'YVpdl2xfKss', 'title' => 'Lā ilāha illa-llāh',  'by' => 'Sufi Centre Rabbaniyya', 'tag' => 'Halaqa' ],
	];
}
?>
<main class="la-app la-app--witness la-dhikr-live mode-witness" data-dhikr-mode="witness">

	<?php // Wave 95b mode-switcher pills ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Breathe</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Focus</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>" aria-current="page">Witness</a>
	</nav>

	<div class="wit-reel" data-wit>

		<?php // YT iframe stage — cover-cropped portrait ?>
		<div class="wit-reel-stage">
			<div class="wit-reel-frame" data-wit-frame>
				<div id="la-wit-player" data-wit-player></div>
			</div>
		</div>
		<div class="wit-reel-scrim" aria-hidden="true"></div>
		<div class="wit-reel-flash" data-wit-flash aria-hidden="true" hidden></div>

		<?php // Tap + swipe overlay ?>
		<div class="wit-touch" data-wit-touch aria-hidden="true"></div>

		<?php // Play button shown when paused ?>
		<button type="button" class="wit-reel-playbtn" data-wit-playbtn aria-label="Play" hidden>
			<svg width="30" height="30" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M9 6l8 6-8 6V6z"/></svg>
		</button>

		<?php // Right rail — Sound + Videos ?>
		<div class="reel-rail">
			<button type="button" class="reel-rbtn" data-wit-mute aria-label="Toggle sound" aria-pressed="false">
				<span class="reel-ric">
					<svg data-wit-mute-icon  width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
					<svg data-wit-vol-icon   width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
				</span>
				<span class="reel-rl" data-wit-mute-label>Sound</span>
			</button>
			<button type="button" class="reel-rbtn" data-wit-videos aria-label="Choose a recitation">
				<span class="reel-ric"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
				<span class="reel-rl">Videos</span>
			</button>
		</div>

		<?php // Progress pips ?>
		<div class="reel-pips" data-wit-pips>
			<?php foreach ( $la_wit_videos as $i => $v ) : ?>
				<span class="rpip <?php echo $i === 0 ? 'on' : ''; ?>" data-wit-pip="<?php echo (int) $i; ?>"></span>
			<?php endforeach; ?>
		</div>

		<?php // Caption ?>
		<div class="reel-caption">
			<div class="reel-cap-tag"><span class="reel-karaoke">Karaoke</span> · follow along</div>
			<div class="reel-cap-title" data-wit-cap-title><?php echo esc_html( $la_wit_videos[0]['title'] ); ?></div>
			<div class="reel-cap-by" data-wit-cap-by><?php echo esc_html( $la_wit_videos[0]['by'] ); ?> · <?php echo esc_html( $la_wit_videos[0]['tag'] ); ?></div>
		</div>

		<?php // Hints — "tap for sound" while muted, "swipe for another" always ?>
		<button type="button" class="reel-soundhint" data-wit-soundhint aria-label="Tap for sound">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
			Tap for sound
		</button>
		<div class="reel-swipehint">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
			Swipe for another
		</div>

		<?php // Videos bottom sheet ?>
		<div class="sheet-backdrop" data-wit-sheet-close hidden></div>
		<div class="sheet" data-wit-sheet hidden role="dialog" aria-modal="true">
			<div class="sheet-grab" aria-hidden="true"></div>
			<div class="sheet-title">Choose a recitation</div>
			<div class="sheet-list">
				<?php foreach ( $la_wit_videos as $i => $v ) : ?>
					<button type="button" class="video-opt <?php echo $i === 0 ? 'sel' : ''; ?>"
						data-wit-pick="<?php echo (int) $i; ?>">
						<span class="video-opt-thumb" style="background-image: url('https://i.ytimg.com/vi/<?php echo esc_attr( $v['id'] ); ?>/mqdefault.jpg');"></span>
						<span class="video-opt-meta">
							<b><?php echo esc_html( $v['title'] ); ?></b>
							<span><?php echo esc_html( $v['by'] ); ?> · <?php echo esc_html( $v['tag'] ); ?></span>
						</span>
						<span class="phrase-opt-check" aria-hidden="true">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<script id="la-wit-config" type="application/json">
		<?php echo wp_json_encode( $la_wit_videos ); ?>
	</script>

	<script>
	/* Wave 99: WitnessReel state machine.
	   Uses the full YouTube IFrame Player API (lazy-loaded) so we can
	   loadVideoById on swipe without recreating the iframe each time.
	   Without the API, swapping src reloads the player chrome and
	   flashes the YouTube logo — janky for a karaoke reel.

	   Autoplay requires mute=1 on first load. Unmute fires on user
	   gesture (tap on the Sound button), which is what the "Tap for
	   sound" hint nudges users toward. */
	(function() {
		const root = document.querySelector('[data-wit]');
		if ( ! root ) return;
		const VIDS = JSON.parse( document.getElementById('la-wit-config').textContent );
		if ( ! VIDS.length ) return;

		let idx     = 0;
		let muted   = true;
		let playing = true;
		let player  = null;

		const playerEl   = root.querySelector('#la-wit-player');
		const flashEl    = root.querySelector('[data-wit-flash]');
		const touch      = root.querySelector('[data-wit-touch]');
		const playbtn    = root.querySelector('[data-wit-playbtn]');
		const muteBtn    = root.querySelector('[data-wit-mute]');
		const muteIcon   = root.querySelector('[data-wit-mute-icon]');
		const volIcon    = root.querySelector('[data-wit-vol-icon]');
		const muteLabel  = root.querySelector('[data-wit-mute-label]');
		const videosBtn  = root.querySelector('[data-wit-videos]');
		const pips       = root.querySelectorAll('[data-wit-pip]');
		const capTitle   = root.querySelector('[data-wit-cap-title]');
		const capBy      = root.querySelector('[data-wit-cap-by]');
		const soundhint  = root.querySelector('[data-wit-soundhint]');
		const sheetScrim = root.querySelector('[data-wit-sheet-close]');
		const sheet      = root.querySelector('[data-wit-sheet]');
		const picks      = root.querySelectorAll('[data-wit-pick]');

		function render() {
			pips.forEach( (p, i) => p.classList.toggle('on', i === idx) );
			capTitle.textContent = VIDS[idx].title;
			capBy.textContent    = VIDS[idx].by + ' · ' + VIDS[idx].tag;
			muteBtn.classList.toggle('on', ! muted);
			muteBtn.setAttribute('aria-pressed', String( ! muted ));
			if ( muteIcon ) muteIcon.hidden = ! muted;
			if ( volIcon  ) volIcon.hidden  =   muted;
			muteLabel.textContent = muted ? 'Sound' : 'On';
			soundhint.hidden = ( ! muted );
			playbtn.hidden = playing;
			picks.forEach( (b, i) => b.classList.toggle('sel', i === idx) );
		}

		function loadAPI() {
			return new Promise( resolve => {
				if ( window.YT && window.YT.Player ) return resolve(window.YT);
				if ( ! document.querySelector('script[src*="youtube.com/iframe_api"]') ) {
					const tag = document.createElement('script');
					tag.src = 'https://www.youtube.com/iframe_api';
					document.head.appendChild(tag);
				}
				const prev = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = function() {
					if ( typeof prev === 'function' ) try { prev(); } catch (_) {}
					resolve(window.YT);
				};
			});
		}

		loadAPI().then( YT => {
			player = new YT.Player(playerEl, {
				videoId: VIDS[0].id,
				host: 'https://www.youtube-nocookie.com',
				playerVars: {
					autoplay: 1, mute: 1, controls: 0, playsinline: 1,
					rel: 0, modestbranding: 1, loop: 1,
					playlist: VIDS[0].id,
					iv_load_policy: 3, fs: 0, disablekb: 1,
				},
				events: {
					onReady: (e) => { try { e.target.mute(); e.target.playVideo(); } catch(_) {} },
				},
			});
		});

		function goTo(newIdx) {
			idx = ( newIdx + VIDS.length ) % VIDS.length;
			if ( player && player.loadVideoById ) {
				try { player.loadVideoById({ videoId: VIDS[idx].id }); } catch(_) {}
				setTimeout( () => {
					try {
						if ( muted )   player.mute();   else player.unMute();
						if ( playing ) player.playVideo(); else player.pauseVideo();
					} catch (_) {}
				}, 60 );
			}
			// flash overlay for visual feedback
			flashEl.hidden = false;
			flashEl.style.animation = 'none';
			void flashEl.offsetWidth;
			flashEl.style.animation = '';
			setTimeout( () => { flashEl.hidden = true; }, 500 );
			render();
		}

		function togglePlay() {
			if ( ! player ) return;
			playing = ! playing;
			try { playing ? player.playVideo() : player.pauseVideo(); } catch (_) {}
			render();
		}
		function toggleMute() {
			if ( ! player ) return;
			muted = ! muted;
			try {
				if ( muted ) player.mute();
				else { player.unMute(); player.setVolume(100); }
			} catch (_) {}
			render();
		}

		// Tap + vertical swipe
		const drag = { y: 0, active: false, moved: false, dy: 0 };
		touch.addEventListener('pointerdown', (e) => {
			drag.y = e.clientY; drag.active = true; drag.moved = false; drag.dy = 0;
		});
		touch.addEventListener('pointermove', (e) => {
			if ( ! drag.active ) return;
			const dy = e.clientY - drag.y;
			if ( Math.abs(dy) > 8 ) drag.moved = true;
			drag.dy = dy;
		});
		const onUp = (e) => {
			if ( ! drag.active ) return;
			const dy = drag.dy;
			drag.active = false;
			if      ( drag.moved && Math.abs(dy) > 48 ) goTo( idx + ( dy < 0 ? 1 : -1 ) );
			else if ( ! drag.moved )                    togglePlay();
		};
		touch.addEventListener('pointerup',     onUp);
		touch.addEventListener('pointercancel', onUp);

		muteBtn.addEventListener('click',   toggleMute);
		soundhint.addEventListener('click', toggleMute);
		videosBtn.addEventListener('click', () => {
			sheet.hidden = false;
			sheetScrim.hidden = false;
		});
		sheetScrim.addEventListener('click', () => {
			sheet.hidden = true;
			sheetScrim.hidden = true;
		});
		picks.forEach( b => b.addEventListener('click', () => {
			goTo( parseInt(b.dataset.witPick, 10) );
			sheet.hidden = true;
			sheetScrim.hidden = true;
		}) );

		render();
	})();
	</script>
</main>
