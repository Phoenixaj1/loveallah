/* =========================================================
   Love Allah — TikTok-style feed interactions
   Snap scroll · in-feed dhikr completion · autoplay-on-scroll
   · scholar-affinity signals
   ========================================================= */
(function() {
	'use strict';

	if ( typeof LA === 'undefined' ) return;

	const $  = (s, el) => (el || document).querySelector(s);
	const $$ = (s, el) => Array.from((el || document).querySelectorAll(s));

	// ============================================================
	// AUDIO (Web Audio synthesis)
	// ============================================================
	let _audioCtx = null;
	function getAudio() {
		try {
			if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
			if (_audioCtx.state === 'suspended') _audioCtx.resume();
			return _audioCtx;
		} catch (e) { return null; }
	}
	function tasbeehClick() {
		const ctx = getAudio(); if (!ctx) return;
		const osc = ctx.createOscillator();
		const gain = ctx.createGain();
		osc.type = 'sine';
		osc.frequency.setValueAtTime(720, ctx.currentTime);
		osc.frequency.exponentialRampToValueAtTime(420, ctx.currentTime + 0.08);
		gain.gain.setValueAtTime(0.0001, ctx.currentTime);
		gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.005);
		gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);
		osc.connect(gain).connect(ctx.destination);
		osc.start();
		osc.stop(ctx.currentTime + 0.14);
	}
	function unlockChime() {
		const ctx = getAudio(); if (!ctx) return;
		[528, 792, 1056].forEach((f, i) => {
			const osc = ctx.createOscillator();
			const gain = ctx.createGain();
			osc.type = 'sine'; osc.frequency.value = f;
			const peak = 0.16 - i * 0.05;
			gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.02);
			gain.gain.linearRampToValueAtTime(peak, ctx.currentTime + i * 0.02 + 0.03);
			gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.02 + 1.4);
			osc.connect(gain).connect(ctx.destination);
			osc.start(ctx.currentTime + i * 0.02);
			osc.stop(ctx.currentTime + i * 0.02 + 1.5);
		});
	}
	function haptic(p) { try { navigator.vibrate && navigator.vibrate(p); } catch(e) {} }

	// ============================================================
	// PRAYER COUNTDOWN
	// ============================================================
	(function prayerCountdown() {
		const host = document.querySelector('.la-header[data-next-time]');
		if (!host) return;
		const nextTime = host.dataset.nextTime;
		const out = $('[data-countdown]', host);
		if (!nextTime || !out) return;
		const tick = () => {
			const [h, m] = nextTime.split(':').map(Number);
			const now = new Date();
			const target = new Date();
			target.setHours(h, m, 0, 0);
			if (target < now) target.setDate(target.getDate() + 1);
			let diff = Math.max(0, target - now);
			const hrs = Math.floor(diff / 3600000);
			const mins = Math.floor((diff % 3600000) / 60000);
			const secs = Math.floor((diff % 60000) / 1000);
			let txt;
			if (hrs > 0) txt = `${hrs}h ${mins}m`;
			else if (mins > 0) txt = `${mins}m ${String(secs).padStart(2,'0')}s`;
			else txt = `${secs}s`;
			out.textContent = `in ${txt}`;
		};
		tick();
		setInterval(tick, 1000);
	})();

	// ============================================================
	// HEADER ACTIONS — GPS / Search / Bell
	// ============================================================
	(function headerActions() {
		const locateBtn = document.querySelector('[data-action="locate"]');
		const searchBtn = document.querySelector('[data-action="search"]');
		const bellBtn   = document.querySelector('[data-action="notifications"]');
		const eventsBtn = document.querySelector('[data-action="events"]');

		if (locateBtn) {
			locateBtn.addEventListener('click', () => {
				if (!navigator.geolocation) return alert('Location not supported.');
				locateBtn.classList.add('is-busy');
				navigator.geolocation.getCurrentPosition(async pos => {
					try {
						const res = await fetch(`${LA.apiRoot}mosques/nearest?lat=${pos.coords.latitude}&lng=${pos.coords.longitude}`);
						const data = await res.json();
						if (!data.mosques || !data.mosques.length) { alert('No masjid nearby yet.'); return; }
						const m = data.mosques[0];
						if (confirm(`Closest: ${m.name} (${m.distance_km}km). Set as your masjid?`)) {
							await fetch(`${LA.apiRoot}choose-mosque`, {
								method: 'POST',
								headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': LA.nonce },
								body: JSON.stringify({ slug: m.slug }),
							});
							location.reload();
						}
					} catch (e) { console.error(e); }
					finally { locateBtn.classList.remove('is-busy'); }
				}, () => { locateBtn.classList.remove('is-busy'); alert('Could not get your location.'); });
			});
		}
		if (searchBtn) searchBtn.addEventListener('click', () => alert('Search is coming soon.'));
		if (bellBtn) bellBtn.addEventListener('click', () => {
			const dot = bellBtn.querySelector('.la-icon-btn-dot');
			if (dot) dot.classList.remove('is-on');
			alert('Notifications coming soon — both from your masjid and from Love Allah.');
		});
		if (eventsBtn) eventsBtn.addEventListener('click', () => openEventsSheet());
	})();

	// ============================================================
	// EVENTS BOTTOM SHEET
	// ============================================================
	const sheetBackdrop = document.querySelector('[data-sheet-backdrop]');
	const eventsSheet   = document.querySelector('[data-sheet="events"]');

	function openSheet(el) {
		if (!el || !sheetBackdrop) return;
		el.hidden = false;
		sheetBackdrop.hidden = false;
		requestAnimationFrame(() => {
			el.classList.add('is-open');
			sheetBackdrop.classList.add('is-open');
		});
	}
	function closeSheet(el) {
		if (!el || !sheetBackdrop) return;
		el.classList.remove('is-open');
		sheetBackdrop.classList.remove('is-open');
		setTimeout(() => {
			el.hidden = true;
			sheetBackdrop.hidden = true;
		}, 320);
	}

	sheetBackdrop?.addEventListener('click', () => {
		document.querySelectorAll('.la-sheet.is-open').forEach(closeSheet);
	});
	document.addEventListener('click', (e) => {
		const closer = e.target.closest('[data-sheet-close]');
		if (closer) {
			const sheet = closer.closest('.la-sheet');
			if (sheet) closeSheet(sheet);
		}
	});

	async function openEventsSheet() {
		if (!eventsSheet) return;
		openSheet(eventsSheet);
		const body = eventsSheet.querySelector('[data-events-body]');
		const mosqueEl = eventsSheet.querySelector('[data-events-mosque]');
		if (body) body.innerHTML = '<p class="la-sheet-loading">Loading events…</p>';

		try {
			const res = await fetch(LA.apiRoot + 'events/upcoming', {
				headers: { 'X-LA-Session': LA.sessionId },
			});
			const data = await res.json();
			if (mosqueEl) mosqueEl.textContent = data.mosque?.name || 'Your masjid';

			if (!data.events || !data.events.length) {
				body.innerHTML = '<p class="la-sheet-empty">No events scheduled yet. Check back soon.</p>';
				return;
			}
			body.innerHTML = data.events.map(renderEvent).join('');
		} catch (err) {
			body.innerHTML = '<p class="la-sheet-empty">Couldn\'t load events. Try again later.</p>';
			console.error('[loveallah] events fetch failed', err);
		}
	}

	function renderEvent(e) {
		const d = new Date(e.starts_at.replace(' ', 'T') + 'Z');
		const day = d.getUTCDate();
		const month = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getUTCMonth()];
		const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		const weekday = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getUTCDay()];

		const tagHtml = e.tag ? `<span class="la-event-tag">${escapeHtml(e.tag)}</span>` : '';
		const locHtml = e.location ? `<span>📍 ${escapeHtml(e.location)}</span>` : '';

		return `
		<article class="la-event">
			<div class="la-event-date">
				<div class="la-event-day">${day}</div>
				<div class="la-event-month">${month}</div>
			</div>
			<div class="la-event-body">
				${tagHtml}
				<h3 class="la-event-title">${escapeHtml(e.title)}</h3>
				<div class="la-event-meta">
					<span>🕒 ${weekday} · ${escapeHtml(time)}</span>
					${locHtml}
				</div>
				${e.description ? `<p class="la-event-desc">${escapeHtml(e.description)}</p>` : ''}
			</div>
		</article>`;
	}

	// expose `closeSheet` so spotlight code can use it later
	window.__la_closeSheet = closeSheet;

	// ============================================================
	// SNAP FEED — autoplay, dhikr completion, interactions
	// ============================================================
	const feedContainer = $('[data-feed]');
	if (!feedContainer) return;

	// ============================================================
	// FEED FILTER CHIPS
	// ============================================================
	let currentFilter = '';
	const chipsBar = $('[data-feed-chips]');
	chipsBar?.addEventListener('click', async (e) => {
		const chip = e.target.closest('.la-feed-chip');
		if (!chip) return;
		const newType = chip.dataset.type || '';
		if (newType === currentFilter) return;
		$$('.la-feed-chip', chipsBar).forEach(c => c.classList.toggle('is-active', c === chip));
		currentFilter = newType;
		haptic(8);
		await reloadFeedForFilter(newType);
	});

	async function reloadFeedForFilter(type) {
		// Reset infinite scroll cursor
		currentPage = 0;
		exhausted = false;
		loadingMore = false;
		// Wipe current cards (keep sentinel + loader)
		$$('.la-snap', feedContainer).forEach(card => card.remove());
		// Show loading placeholder
		const refNode = loader || sentinel;
		if (refNode) {
			refNode.insertAdjacentHTML('beforebegin', '<article class="la-snap la-snap--loading"><div class="la-snap-inner"><div class="la-feed-loader-spinner"></div></div></article>');
		}
		// Fetch page 0 with filter
		try {
			const qs = `page=0&limit=10${type ? '&type=' + encodeURIComponent(type) : ''}`;
			const res = await fetch(`${LA.apiRoot}feed/more?${qs}`, {
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
			const data = await res.json();
			$$('.la-snap--loading', feedContainer).forEach(n => n.remove());
			if (data.html && data.count > 0) {
				refNode.insertAdjacentHTML('beforebegin', data.html);
				observeNewCards();
				// Scroll back to top
				feedContainer.scrollTo({ top: 0, behavior: 'smooth' });
			} else {
				refNode.insertAdjacentHTML('beforebegin',
					`<article class="la-snap la-snap--empty"><div class="la-snap-inner"><h3>No ${type || 'content'} yet</h3><p>Check back soon — we curate fresh content every day.</p></div></article>`);
			}
		} catch (err) {
			console.error('[loveallah] filter reload failed', err);
		}
	}

	const AFFIRMATIONS = [
		'I love my Lord',
		'Bring yourself closer to Allah',
		'My heart finds rest in His remembrance',
		'He is closer than my jugular vein',
		'Speak His name, the soul lifts',
	];
	function pickAffirmation() { return AFFIRMATIONS[Math.floor(Math.random()*AFFIRMATIONS.length)]; }

	function showAffirmation(text, dur = 1800) {
		return new Promise(resolve => {
			const overlay = $('.la-affirmation-overlay');
			const textEl  = overlay && $('.la-affirmation-text', overlay);
			if (!overlay || !textEl) { resolve(); return; }
			textEl.textContent = text;
			overlay.hidden = false;
			requestAnimationFrame(() => overlay.classList.add('is-visible'));
			setTimeout(() => {
				overlay.classList.remove('is-visible');
				setTimeout(() => { overlay.hidden = true; resolve(); }, 600);
			}, dur);
		});
	}

	// Dhikr completion via scroll-past: card was seen, then user swipes to next
	async function completeDhikrOnSwipe(card) {
		if (card.dataset.busy === '1' || card.classList.contains('is-done')) return;
		card.dataset.busy = '1';

		let data;
		try {
			const res = await fetch(LA.apiRoot + 'dhikr/complete', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
			data = await res.json();
			if (!res.ok) throw new Error(data.message || 'failed');
		} catch (err) {
			card.dataset.busy = '0';
			console.error('[loveallah] dhikr complete failed', err);
			return;
		}

		tasbeehClick();
		haptic(12);
		card.classList.add('is-done');

		if (data.unlocked) {
			unlockChime();
			haptic([40, 60, 40]);
			// Sacred-pause: 2.5s of held silence on deep night before unlock affirmation.
			// This is the moment that separates "I unlocked content" from "I just did something."
			setTimeout(() => showSacredPause(), 350);
		}
		card.dataset.busy = '0';
	}

	// ─── Sacred-pause overlay (post-5th-dhikr held silence) ───
	function showSacredPause() {
		// Pool of ayat suited to the moment of completed dhikr
		const verses = [
			{
				arabic: 'ٱللَّهُ نُورُ ٱلسَّمَـٰوَٰتِ وَٱلْأَرْضِ',
				meaning: 'Allah is the Light of the heavens and the earth — al-Nur 24:35',
			},
			{
				arabic: 'فَٱذْكُرُونِىٓ أَذْكُرْكُمْ',
				meaning: 'So remember Me — I will remember you — al-Baqarah 2:152',
			},
			{
				arabic: 'إِنَّ مَعَ ٱلْعُسْرِ يُسْرًۭا',
				meaning: 'Indeed, with hardship comes ease — al-Sharh 94:6',
			},
			{
				arabic: 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ',
				meaning: 'In the name of Allah, the Most Gracious, the Most Merciful',
			},
			{
				arabic: 'وَهُوَ مَعَكُمْ أَيْنَ مَا كُنتُمْ',
				meaning: 'And He is with you wherever you are — al-Hadid 57:4',
			},
		];
		const pick = verses[Math.floor(Math.random() * verses.length)];

		let overlay = document.querySelector('.la-sacred-pause');
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.className = 'la-sacred-pause';
			overlay.innerHTML = `
				<div class="la-sacred-pause-ayah" dir="rtl" lang="ar"></div>
				<div class="la-sacred-pause-meaning"></div>
			`;
			document.body.appendChild(overlay);
		}
		overlay.querySelector('.la-sacred-pause-ayah').textContent = pick.arabic;
		overlay.querySelector('.la-sacred-pause-meaning').textContent = pick.meaning;

		// Fade in → hold → fade out → then trigger the regular affirmation toast briefly
		requestAnimationFrame(() => overlay.classList.add('is-visible'));
		setTimeout(() => {
			overlay.classList.remove('is-visible');
			setTimeout(() => {
				showAffirmation('Your feed has been unlocked', 1600);
			}, 1100);
		}, 3200);
	}

	// ─── Toast notifications ───
	let toastTimer = null;
	function showToast(msg, dur = 2400) {
		let toast = document.querySelector('.la-toast');
		if (!toast) {
			toast = document.createElement('div');
			toast.className = 'la-toast';
			toast.innerHTML = '<span class="la-toast-text"></span>';
			document.body.appendChild(toast);
		}
		toast.querySelector('.la-toast-text').textContent = msg;
		toast.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(() => toast.classList.remove('is-visible'), dur);
	}

	// ─── Double-tap to like (with heart burst) ───
	// TikTok muscle memory: tap the video → like + spawn burst at touch point.
	const lastTapByCard = new WeakMap();
	feedContainer.addEventListener('pointerdown', (e) => {
		const card = e.target.closest('.la-snap--content');
		if (!card) return;
		// Don't hijack taps on the action rail or overlay text
		if (e.target.closest('.la-snap-actions, .la-snap-overlay a, button')) return;
		const now = Date.now();
		const last = lastTapByCard.get(card) || 0;
		if (now - last < 320) {
			// Double-tap: trigger like + heart burst
			const likeBtn = card.querySelector('.la-snap-action[data-act="like"]');
			if (likeBtn && !likeBtn.classList.contains('is-active')) {
				likeBtn.click();
			}
			spawnHeartBurst(card, e.clientX, e.clientY);
			lastTapByCard.set(card, 0); // reset so triple-tap doesn't keep firing
		} else {
			lastTapByCard.set(card, now);
		}
	});

	function spawnHeartBurst(card, clientX, clientY) {
		const rect = card.getBoundingClientRect();
		const burst = document.createElement('div');
		burst.className = 'la-heart-burst';
		burst.innerHTML = '<svg viewBox="0 0 24 24" fill="#ED1C6C"><path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 4 0 6.5 4 4.5 8C19 16.5 12 21 12 21z"/></svg>';
		burst.style.left = (clientX - rect.left - 40) + 'px';
		burst.style.top  = (clientY - rect.top  - 40) + 'px';
		card.appendChild(burst);
		setTimeout(() => burst.remove(), 900);
		haptic(15);
	}

	// ─── Action buttons (like / save / share) ───
	feedContainer.addEventListener('click', async (e) => {
		const action = e.target.closest('.la-snap-action[data-act]');
		if (!action) return;
		const id = action.dataset.id;
		const act = action.dataset.act;
		const card = action.closest('.la-snap');
		haptic(8);

		try {
			await fetch(`${LA.apiRoot}feed/${id}/${act}`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
		} catch (err) { console.error(err); }

		if (act === 'like') {
			action.classList.toggle('is-active');
			// Pop the button + tick the count with a slide animation
			action.classList.remove('is-pop');
			void action.offsetWidth; // restart animation
			action.classList.add('is-pop');
			const span = action.querySelector('[data-likes]');
			if (span) {
				const newVal = parseInt(span.textContent || '0', 10) + (action.classList.contains('is-active') ? 1 : -1);
				span.textContent = Math.max(0, newVal);
				span.classList.remove('is-tick');
				void span.offsetWidth;
				span.classList.add('is-tick');
			}
		}

		if (act === 'save') {
			const saved = action.classList.toggle('is-active');
			// Mirror to localStorage so a "Saved" view can read it without server roundtrip
			try {
				const saves = new Set(JSON.parse(localStorage.getItem('la_saved') || '[]'));
				saved ? saves.add(id) : saves.delete(id);
				localStorage.setItem('la_saved', JSON.stringify([...saves]));
			} catch (e) {}
			showToast(saved ? 'Saved · view in your library' : 'Removed from saved');
		}

		if (act === 'share') {
			await shareCard(card, id);
		}
	});

	async function shareCard(card, postId) {
		const titleEl = card.querySelector('.la-snap-title');
		const scholarEl = card.querySelector('.la-snap-scholar-name');
		const title = `${scholarEl?.textContent.trim() || 'Love Allah'} — ${titleEl?.textContent.trim() || ''}`.trim();
		const url   = `${location.origin}/feed/post/${postId}?ref=share`;
		const text  = `Watch this on Love Allah`;

		if (navigator.share) {
			try {
				await navigator.share({ title, text, url });
				showToast('Shared');
			} catch (err) {
				if (err && err.name === 'AbortError') return; // user cancelled
				console.error('share failed', err);
			}
			return;
		}
		// Fallback: copy URL
		try {
			await navigator.clipboard.writeText(url);
			showToast('Link copied');
		} catch (e) {
			window.prompt('Copy this link:', url);
		}
	}

	// Restore saved-state highlights from localStorage on page load
	requestAnimationFrame(() => {
		try {
			const saves = new Set(JSON.parse(localStorage.getItem('la_saved') || '[]'));
			$$('.la-snap-action[data-act="save"]').forEach(btn => {
				if (saves.has(btn.dataset.id)) btn.classList.add('is-active');
			});
		} catch (e) {}
	});

	// ─── Signup card handlers ───
	feedContainer.addEventListener('submit', async (e) => {
		const form = e.target.closest('[data-signup-form]');
		if (!form) return;
		e.preventDefault();
		const card = form.closest('.la-snap--signup');
		const email = form.querySelector('.la-signup-email')?.value?.trim();
		if (!email) return;

		const submitBtn = form.querySelector('.la-signup-submit');
		if (submitBtn) submitBtn.disabled = true;
		haptic(15);

		try {
			const res = await fetch(`${LA.apiRoot}signup`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
				body: JSON.stringify({ email, source: 'feed' }),
			});
			const data = await res.json();
			if (!res.ok) throw new Error(data.message || 'failed');

			localStorage.setItem('la_captured', '1');
			// Replace card content with thank-you state
			if (card) {
				card.innerHTML = `<div class="la-snap-inner"><div class="la-snap-overline">Bismillāh</div><h2 class="la-snap-signup-title">Welcome to Love Allah</h2><p class="la-snap-signup-body">We'll be in touch. May Allah keep your heart close to His remembrance.</p></div>`;
				card.classList.add('la-snap--signup-done');
				// Auto-scroll to next card
				setTimeout(() => {
					const next = card.nextElementSibling;
					if (next) next.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 1800);
			}
			showToast('Thank you · check your inbox soon');
		} catch (err) {
			console.error('[loveallah] signup failed', err);
			if (submitBtn) submitBtn.disabled = false;
			showToast(err.message || 'Could not sign up. Try again.');
		}
	});

	feedContainer.addEventListener('click', (e) => {
		const skip = e.target.closest('[data-signup-skip]');
		if (!skip) return;
		const card = skip.closest('.la-snap');
		if (!card) return;
		const next = card.nextElementSibling;
		if (next) next.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	// ─── Autoplay / view tracking / dhikr-on-scroll-past ───
	const seenViews = new Set();
	const dhikrSeen = new Set();
	let currentPlaying = null;
	let userWantsSound = sessionStorage.getItem('la_sound') === '1';

	function setSound(on) {
		userWantsSound = !!on;
		sessionStorage.setItem('la_sound', on ? '1' : '0');
		$$('.la-snap-mute').forEach(btn => btn.classList.toggle('is-on', on));
		// Reload current video with new mute state, preserving position is impossible across iframe src changes
		if (currentPlaying) {
			const card = currentPlaying.closest('.la-snap');
			if (card) playVideoIn(card);
		}
	}

	// Apply initial state to any mute buttons on first paint
	requestAnimationFrame(() => {
		$$('.la-snap-mute').forEach(btn => btn.classList.toggle('is-on', userWantsSound));
	});

	feedContainer.addEventListener('click', (e) => {
		const btn = e.target.closest('[data-action="toggle-mute"]');
		if (!btn) return;
		e.stopPropagation();
		haptic(10);
		setSound(!userWantsSound);
	});

	const io = new IntersectionObserver((entries) => {
		entries.forEach(entry => {
			const card = entry.target;
			const type = card.dataset.cardType;

			if (type === 'dhikr') {
				const idx = card.dataset.dhikrIndex;
				if (entry.intersectionRatio >= 0.7) {
					dhikrSeen.add(idx);
				} else if (entry.intersectionRatio < 0.4 && dhikrSeen.has(idx) && !card.classList.contains('is-done')) {
					completeDhikrOnSwipe(card);
				}
				return;
			}

			if (type === 'content') {
				if (entry.intersectionRatio >= 0.7) {
					const id = card.dataset.postId;
					if (id && !seenViews.has(id)) {
						seenViews.add(id);
						fetch(`${LA.apiRoot}feed/${id}/view`, {
							method: 'POST',
							headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
						}).catch(() => {});
					}
					playVideoIn(card);
				} else if (entry.intersectionRatio < 0.3) {
					pauseVideoIn(card);
				}
			}
		});
	}, { threshold: [0, 0.3, 0.4, 0.7, 1], root: feedContainer });

	function observeNewCards() {
		$$('.la-snap:not([data-observed])', feedContainer).forEach(card => {
			card.dataset.observed = '1';
			io.observe(card);
		});
	}
	observeNewCards();

	// ============================================================
	// INFINITE SCROLL — sentinel triggers next batch
	// ============================================================
	const sentinel = $('[data-feed-sentinel]', feedContainer);
	const loader   = $('[data-feed-loader]', feedContainer);
	let currentPage = 0;
	let loadingMore = false;
	let exhausted = false;

	if (sentinel) {
		const sentinelIO = new IntersectionObserver((entries) => {
			if (entries[0].isIntersecting && !loadingMore && !exhausted) {
				loadMore();
			}
		}, {
			root: feedContainer,
			rootMargin: '1200px 0px 1200px 0px', // prefetch well before user reaches end
			threshold: 0,
		});
		sentinelIO.observe(sentinel);
	}

	async function loadMore() {
		if (loadingMore || exhausted) return;
		loadingMore = true;
		currentPage += 1;
		if (loader) loader.hidden = false;

		try {
			const qs = `page=${currentPage}&limit=10${currentFilter ? '&type=' + encodeURIComponent(currentFilter) : ''}`;
			const res = await fetch(`${LA.apiRoot}feed/more?${qs}`, {
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
			const data = await res.json();
			if (!res.ok) throw new Error(data.message || 'failed');
			if (!data.html || data.count === 0) {
				exhausted = true;
				if (loader) loader.hidden = true;
				return;
			}
			// Insert before sentinel & loader
			const refNode = loader || sentinel;
			refNode.insertAdjacentHTML('beforebegin', data.html);
			observeNewCards();
		} catch (err) {
			console.error('[loveallah] load more failed', err);
			currentPage -= 1; // retry next time
		} finally {
			loadingMore = false;
			if (loader) loader.hidden = true;
		}
	}

	function playVideoIn(card) {
		const iframe = card.querySelector('.la-snap-iframe');
		if (!iframe || !iframe.dataset.src) return;
		const wanted = iframe.dataset.src;
		// Extract video ID from /embed/XYZ for the loop trick (forces self-replay
		// instead of YouTube's recommended-video end screen — that's where
		// non-Islamic suggestions like Rick Astley sneak in).
		const idMatch = wanted.match(/\/embed\/([\w-]+)/);
		const vid = idMatch ? idMatch[1] : '';
		const muteParam = userWantsSound ? 'mute=0' : 'mute=1';
		// loop=1 + playlist=<self> = video restarts on end, never shows YT's "Up next" overlay.
		// disablekb=1 stops keyboard shortcuts that can open YouTube site.
		// fs=0 disables fullscreen button (we want them staying in our app).
		const loopParams = vid ? `&loop=1&playlist=${vid}` : '';
		const params = `autoplay=1&${muteParam}&playsinline=1&modestbranding=1&rel=0&iv_load_policy=3&cc_load_policy=0&disablekb=1&fs=0&enablejsapi=1${loopParams}`;
		const desired = wanted + (wanted.includes('?') ? '&' : '?') + params;
		if (iframe.src !== desired) iframe.src = desired;
		if (currentPlaying && currentPlaying !== iframe) {
			currentPlaying.src = 'about:blank';
		}
		currentPlaying = iframe;
	}
	function pauseVideoIn(card) {
		const iframe = card.querySelector('.la-snap-iframe');
		if (!iframe) return;
		if (currentPlaying === iframe) currentPlaying = null;
		iframe.src = 'about:blank';
	}

	// Play first content card on load + pulse "tap for sound" hint if muted
	const firstContent = feedContainer.querySelector('.la-snap--content');
	if (firstContent) {
		const r = firstContent.getBoundingClientRect();
		const fr = feedContainer.getBoundingClientRect();
		if (r.top >= fr.top && r.bottom <= fr.bottom + 50) playVideoIn(firstContent);

		// First-card sound hint: if the user hasn't enabled audio yet,
		// pulse a ring around the mute button for ~5s as a discoverability nudge.
		if (!userWantsSound) {
			const muteBtn = firstContent.querySelector('.la-snap-mute');
			if (muteBtn && !sessionStorage.getItem('la_hint_seen')) {
				muteBtn.classList.add('is-hint');
				const stopHint = () => muteBtn.classList.remove('is-hint');
				muteBtn.addEventListener('pointerdown', () => {
					stopHint();
					sessionStorage.setItem('la_hint_seen', '1');
				}, { once: true });
				setTimeout(stopHint, 5500);
			}
		}
	}
})();

/* =========================================================
   Geo prayer-times strip — runs on EVERY page (header is sticky)
   ========================================================= */
(function() {
	'use strict';
	if (typeof LA === 'undefined') return;

	const header   = document.querySelector('.la-header');
	if (!header) return;
	const prayerBar = header.querySelector('[data-prayer-bar]');
	const geoBtn   = header.querySelector('[data-action="use-geo"]');

	// Countdown timer for the active prayer
	function startCountdown() {
		const nextTime = header.getAttribute('data-next-time');
		const nextName = header.getAttribute('data-next-name');
		if (!nextTime) return;
		const etaEl = header.querySelector('[data-countdown]');
		if (!etaEl) return;

		function tick() {
			const now = new Date();
			const [hh, mm] = nextTime.split(':').map(n => parseInt(n, 10));
			const target = new Date(now); target.setHours(hh, mm, 0, 0);
			if (target < now) target.setDate(target.getDate() + 1);
			const diffMs = target - now;
			const totalMin = Math.floor(diffMs / 60000);
			const h = Math.floor(totalMin / 60);
			const m = totalMin % 60;
			etaEl.textContent = h > 0 ? `in ${h}h ${m}m` : `in ${m}m`;
		}
		tick();
		setInterval(tick, 30000);
	}
	startCountdown();

	// Re-render the prayer bar with new times from server
	async function fetchAndRender(lat, lng, label) {
		try {
			const url = new URL(LA.apiRoot + 'prayer-times');
			url.searchParams.set('lat', lat);
			url.searchParams.set('lng', lng);
			url.searchParams.set('tz', Intl.DateTimeFormat().resolvedOptions().timeZone || '');
			const res = await fetch(url, { headers: { 'X-WP-Nonce': LA.nonce } });
			if (!res.ok) throw new Error('fetch failed');
			const data = await res.json();
			if (!data.timings) throw new Error('no timings');

			// Update each cell + the next-prayer state
			const cells = prayerBar?.querySelectorAll('.la-prayer-cell') || [];
			cells.forEach(cell => {
				const name = cell.getAttribute('data-prayer-name');
				const t = data.timings[name];
				if (t) cell.querySelector('.la-prayer-cell-time').textContent = t;
				cell.classList.toggle('is-next', name === data.next.name);
			});
			header.setAttribute('data-next-time', data.next.time);
			header.setAttribute('data-next-name', data.next.name);

			// Update tooltip label
			if (geoBtn && label) geoBtn.title = label + ' · prayer times set';
			// Restart countdown with new target
			startCountdown();
		} catch (err) {
			console.warn('[loveallah] prayer-times fetch failed', err);
		}
	}

	// On geo button tap → request precise location, persist, refresh times.
	geoBtn?.addEventListener('click', async () => {
		if (!('geolocation' in navigator)) {
			alert('Your browser does not support location. Prayer times remain set to the default.');
			return;
		}
		geoBtn.classList.add('is-loading');
		navigator.geolocation.getCurrentPosition(
			async (pos) => {
				const lat = pos.coords.latitude;
				const lng = pos.coords.longitude;
				// Try to reverse-geocode the city label via free service.
				// Falls back to "your location" if it fails.
				let label = 'Your location';
				try {
					const geo = await fetch(`https://geocode.maps.co/reverse?lat=${lat}&lon=${lng}&format=json`, { cache: 'force-cache' });
					if (geo.ok) {
						const j = await geo.json();
						label = j.address?.city || j.address?.town || j.address?.village || j.address?.county || label;
					}
				} catch (_) {}
				// 30-day cookie — wordpress_* prefix ensures Varnish doesn't strip it.
				const cookieVal = `${lat.toFixed(4)}|${lng.toFixed(4)}|${encodeURIComponent(label)}`;
				const exp = new Date(Date.now() + 30 * 86400000).toUTCString();
				document.cookie = `wordpress_la_geo=${cookieVal}; expires=${exp}; path=/; SameSite=Lax`;
				header.setAttribute('data-geo-lat', lat);
				header.setAttribute('data-geo-lng', lng);
				geoBtn.classList.remove('is-loading');
				await fetchAndRender(lat, lng, label);
			},
			(err) => {
				geoBtn.classList.remove('is-loading');
				console.warn('[loveallah] geolocation denied', err);
			},
			{ enableHighAccuracy: false, timeout: 8000, maximumAge: 24 * 3600 * 1000 }
		);
	});

	// ─── Duas snap feed — Ameen / Save / Share ───
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('.la-dua-snap-action');
		if (!btn) return;
		const card = btn.closest('.la-dua-snap');
		if (!card) return;
		const action = btn.dataset.action;
		const id = btn.dataset.id;
		if (!id) return;

		if (action === 'ameen') {
			// Optimistic flip
			const was = btn.classList.contains('is-active');
			btn.classList.toggle('is-active', !was);
			btn.setAttribute('aria-pressed', String(!was));
			btn.classList.remove('is-popping');
			void btn.offsetWidth;
			btn.classList.add('is-popping');
			const countEl = btn.querySelector('[data-ameen-count]');
			if (countEl) {
				const cur = parseInt(countEl.textContent || '0', 10);
				countEl.textContent = Math.max(0, cur + (was ? -1 : 1));
			}
			if (navigator.vibrate) navigator.vibrate(was ? [12, 30, 12] : [18, 24, 30]);
			try {
				const res = await fetch(`${LA.apiRoot}duas/${id}/ameen`, {
					method: 'POST',
					headers: { 'X-WP-Nonce': LA.nonce, 'Content-Type': 'application/json' },
					credentials: 'include',
				});
				if (!res.ok) throw new Error('failed');
				const data = await res.json();
				btn.classList.toggle('is-active', !!data.ameen);
				btn.setAttribute('aria-pressed', String(!!data.ameen));
				if (countEl) countEl.textContent = data.count || 0;
			} catch (_) {
				// Rollback
				btn.classList.toggle('is-active', was);
				btn.setAttribute('aria-pressed', String(was));
			}
			return;
		}

		// Save + Share use the dua card text
		const arabic = card.querySelector('.la-dua-snap-arabic')?.textContent.trim() || '';
		const translit = card.querySelector('.la-dua-snap-translit')?.textContent.trim() || '';
		const meaning = card.querySelector('.la-dua-snap-meaning')?.textContent.trim() || '';
		const source = card.querySelector('.la-dua-snap-source')?.textContent.trim() || '';
		const title = card.querySelector('.la-dua-snap-title')?.textContent.trim() || '';
		const txt = [title, arabic, translit, meaning, source && '— ' + source].filter(Boolean).join('\n\n');

		if (action === 'save') {
			try {
				await navigator.clipboard.writeText(txt);
				btn.classList.add('is-active');
				const lbl = btn.querySelector('.la-dua-snap-action-label');
				const orig = lbl.textContent;
				lbl.textContent = 'Copied';
				if (navigator.vibrate) navigator.vibrate(15);
				setTimeout(() => { btn.classList.remove('is-active'); lbl.textContent = orig; }, 1600);
			} catch (_) {}
		} else if (action === 'share') {
			if (navigator.share) {
				try {
					await navigator.share({ title: 'Love Allah · ' + title, text: txt, url: location.origin + '/duas/' });
					if (navigator.vibrate) navigator.vibrate(15);
				} catch (_) {}
			} else {
				try { await navigator.clipboard.writeText(txt); btn.querySelector('.la-dua-snap-action-label').textContent = 'Copied'; } catch (_) {}
			}
		}
	});

	// ─── Tasbeeh counter (only on /dhikr) ───
	function initTasbeeh(root) {
		const cfgEl = root.querySelector('#la-tasbeeh-config');
		if (!cfgEl) return;
		let phrases = [];
		try { phrases = JSON.parse(cfgEl.textContent); } catch (_) { return; }
		if (!Array.isArray(phrases) || !phrases.length) return;

		const bead     = root.querySelector('[data-tasbeeh-bead]');
		const arabicEl = root.querySelector('[data-tasbeeh-arabic]');
		const trEl     = root.querySelector('[data-tasbeeh-translit]');
		const meaningEl= root.querySelector('[data-tasbeeh-meaning]');
		const curEl    = root.querySelector('[data-current]');
		const tgtEl    = root.querySelector('[data-target]');
		const ring     = root.querySelector('.la-tasbeeh-ring-progress');
		const totalEl  = root.querySelector('[data-stat-total]');
		const pills    = root.querySelectorAll('.la-tasbeeh-pill');
		const resetBtn = root.querySelector('[data-tasbeeh-action="reset"]');
		const vibBtn   = root.querySelector('[data-tasbeeh-action="vibrate-toggle"]');
		const soundBtn = root.querySelector('[data-tasbeeh-action="sound-toggle"]');
		const bgIframe = root.querySelector('[data-tasbeeh-bg-iframe]');
		let soundOn = false;

		// localStorage daily state keyed by date
		const todayKey = 'la_tasbeeh_' + new Date().toISOString().slice(0,10);
		let state = {};
		try { state = JSON.parse(localStorage.getItem(todayKey) || '{}'); } catch (_) {}
		// shape: { current: 0, counts: {subhanallah: 0, alhamdulillah: 0, allahuakbar: 0} }
		if (typeof state.current !== 'number') state.current = 0;
		if (typeof state.counts !== 'object') state.counts = {};
		phrases.forEach(p => { if (!(p.key in state.counts)) state.counts[p.key] = 0; });

		let vibrateEnabled = localStorage.getItem('la_tasbeeh_vibrate') !== '0';
		if (vibrateEnabled) vibBtn?.classList.add('is-active');

		// Karaoke video — swap embed src when phrase changes.
		// Initial src is rendered server-side (page-dhikr.php) so the very first
		// load benefits from autoplay-muted being a 'page-initiated' load.
		// We seed currentVideoId from the rendered iframe so we don't re-swap
		// to the same video and lose its play state.
		let currentVideoId = phrases[state.current]?.video || null;
		function swapKaraokeIfNeeded(phrase) {
			if (!bgIframe || !phrase.video) return;
			if (currentVideoId === phrase.video) return;
			currentVideoId = phrase.video;
			const muteParam = soundOn ? 'mute=0' : 'mute=1';
			const params = `autoplay=1&${muteParam}&loop=1&playlist=${phrase.video}&controls=0&modestbranding=1&playsinline=1&rel=0&iv_load_policy=3&cc_load_policy=0&disablekb=1&fs=0&enablejsapi=1`;
			bgIframe.src = `https://www.youtube.com/embed/${phrase.video}?${params}`;
		}

		// Sound toggle — flips mute on the karaoke iframe by reloading with new param
		soundBtn?.addEventListener('click', () => {
			soundOn = !soundOn;
			soundBtn.setAttribute('data-sound-state', soundOn ? 'on' : 'off');
			const lbl = soundBtn.querySelector('[data-sound-label]');
			if (lbl) lbl.textContent = soundOn ? 'Sound on' : 'Sound off';
			// Force re-load of current video with new mute param
			currentVideoId = null;
			swapKaraokeIfNeeded(phrases[state.current]);
			if (navigator.vibrate) navigator.vibrate(15);
		});

		// Pending batch to sync to server (avoid one fetch per tap)
		const pending = {};
		let syncTimer = null;
		function scheduleSync() {
			if (syncTimer) clearTimeout(syncTimer);
			syncTimer = setTimeout(syncToServer, 1200);
		}
		async function syncToServer() {
			const entries = Object.entries(pending).filter(([_, n]) => n > 0);
			if (!entries.length) return;
			Object.keys(pending).forEach(k => pending[k] = 0); // clear before request
			for (const [phrase, count] of entries) {
				try {
					await fetch(LA.apiRoot + 'tasbeeh/increment', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': LA.nonce },
						credentials: 'include',
						body: JSON.stringify({ phrase, count }),
					});
				} catch (_) {}
			}
		}

		function render() {
			const phrase = phrases[state.current];
			const cnt = state.counts[phrase.key] || 0;
			arabicEl.textContent = phrase.arabic;
			trEl.textContent = phrase.translit;
			meaningEl.textContent = phrase.meaning;
			curEl.textContent = cnt;
			tgtEl.textContent = phrase.target;
			// Karaoke background swap when phrase changes
			swapKaraokeIfNeeded(phrase);
			// Ring progress (circumference 2πr, r=92 → ~578)
			const circ = 2 * Math.PI * 92;
			const progress = Math.min(1, cnt / phrase.target);
			ring.setAttribute('stroke-dasharray', String(circ));
			ring.setAttribute('stroke-dashoffset', String(circ * (1 - progress)));
			ring.setAttribute('stroke', phrase.color || '#ED1C6C');
			// Pills state
			pills.forEach((pill, i) => {
				pill.classList.toggle('is-active', i === state.current);
				const k = pill.getAttribute('data-key');
				const c = state.counts[k] || 0;
				const t = phrases[i].target;
				pill.classList.toggle('is-complete', c >= t);
				const countEl = pill.querySelector('[data-pill-count]');
				if (countEl) countEl.textContent = c + '/' + t;
			});
			// Total
			const total = Object.values(state.counts).reduce((a, b) => a + b, 0);
			if (totalEl) totalEl.textContent = total;
			// Save
			localStorage.setItem(todayKey, JSON.stringify(state));
		}

		function tap() {
			const phrase = phrases[state.current];
			state.counts[phrase.key] = (state.counts[phrase.key] || 0) + 1;
			pending[phrase.key] = (pending[phrase.key] || 0) + 1;

			// Haptic + visual tick
			if (vibrateEnabled && navigator.vibrate) navigator.vibrate(12);
			bead.classList.remove('is-tapped');
			void bead.offsetWidth;
			bead.classList.add('is-tapped');

			// Auto-advance when reaching target
			if (state.counts[phrase.key] >= phrase.target) {
				bead.classList.add('is-complete');
				if (vibrateEnabled && navigator.vibrate) navigator.vibrate([30, 60, 30]);
				setTimeout(() => {
					bead.classList.remove('is-complete');
					// Move to next incomplete phrase
					const nextIdx = phrases.findIndex((p, i) => state.counts[p.key] < p.target);
					if (nextIdx >= 0) state.current = nextIdx;
					render();
				}, 700);
			}
			render();
			scheduleSync();
		}

		bead.addEventListener('click', tap);
		// Keyboard accessibility
		bead.addEventListener('keydown', (e) => {
			if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); tap(); }
		});

		// Tap a pill to switch active phrase
		pills.forEach((pill, i) => {
			pill.addEventListener('click', () => {
				state.current = i;
				render();
			});
		});

		// Reset — single tap = confirm prompt (no long-press shortcut for now)
		resetBtn?.addEventListener('click', () => {
			if (!confirm("Reset today's tasbeeh? Your count will return to zero.")) return;
			phrases.forEach(p => { state.counts[p.key] = 0; });
			state.current = 0;
			localStorage.removeItem(todayKey);
			render();
		});

		// Vibration toggle
		vibBtn?.addEventListener('click', () => {
			vibrateEnabled = !vibrateEnabled;
			localStorage.setItem('la_tasbeeh_vibrate', vibrateEnabled ? '1' : '0');
			vibBtn.classList.toggle('is-active', vibrateEnabled);
			if (vibrateEnabled && navigator.vibrate) navigator.vibrate(20);
		});

		render();
		// Hydrate counts from server (in case user used tasbeeh on another device)
		fetch(LA.apiRoot + 'tasbeeh/today', { credentials: 'include' })
			.then(r => r.ok ? r.json() : null)
			.then(data => {
				if (!data || !data.counts) return;
				let changed = false;
				Object.entries(data.counts).forEach(([k, v]) => {
					if ((state.counts[k] || 0) < v) { state.counts[k] = v; changed = true; }
				});
				if (changed) render();
			})
			.catch(() => {});
	}
	const tasbeehEl = document.querySelector('[data-tasbeeh]');
	if (tasbeehEl) initTasbeeh(tasbeehEl);

	// ─── Prayer tracking — tap a prayer cell to mark prayed today ───
	header.addEventListener('click', async (e) => {
		const cell = e.target.closest('.la-prayer-cell[data-action="toggle-prayed"]');
		if (!cell) return;
		const prayer = cell.getAttribute('data-prayer-name');
		if (!prayer) return;
		// Optimistic UI: flip immediately
		const wasPrayed = cell.classList.contains('is-prayed');
		cell.classList.toggle('is-prayed', !wasPrayed);
		cell.setAttribute('aria-pressed', String(!wasPrayed));
		cell.classList.remove('is-toggling');
		void cell.offsetWidth;
		cell.classList.add('is-toggling');
		// Light haptic on mark, heavier on unmark to differentiate
		if (navigator.vibrate) navigator.vibrate(wasPrayed ? [12, 30, 12] : 18);
		try {
			const res = await fetch(LA.apiRoot + 'prayer-log/toggle', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': LA.nonce },
				body: JSON.stringify({ prayer }),
				credentials: 'include',
			});
			if (!res.ok) throw new Error('toggle failed');
			const data = await res.json();
			// Reconcile in case server thinks otherwise (race)
			cell.classList.toggle('is-prayed', !!data.prayed);
			cell.setAttribute('aria-pressed', String(!!data.prayed));
			// Toast confirmation on mark prayed
			if (data.prayed && typeof showToast !== 'undefined') {
				// noop — toast lives in the feed IIFE, only fires inside feed scope
			}
		} catch (err) {
			// Rollback optimistic flip
			cell.classList.toggle('is-prayed', wasPrayed);
			cell.setAttribute('aria-pressed', String(wasPrayed));
			console.warn('[loveallah] prayer-toggle failed', err);
		}
	});
})();
