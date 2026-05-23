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

		// Optimistic UI for like/save toggles
		const wasActive = action.classList.contains('is-active');
		if (act === 'like' || act === 'save') {
			action.classList.toggle('is-active', !wasActive);
			action.setAttribute('aria-pressed', String(!wasActive));
		}

		// Server toggles. Response is { ok, active } — sync UI to server truth.
		let res = null;
		try {
			const r = await fetch(`${LA.apiRoot}feed/${id}/${act}`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
			res = await r.json().catch(() => null);
		} catch (err) { console.error(err); }

		if ((act === 'like' || act === 'save') && res && typeof res.active === 'boolean') {
			// Sync to server response in case optimistic UI was wrong
			action.classList.toggle('is-active', res.active);
			action.setAttribute('aria-pressed', String(res.active));
		}

		if (act === 'like') {
			action.classList.remove('is-pop');
			void action.offsetWidth;
			action.classList.add('is-pop');
			const span = action.querySelector('[data-likes]');
			if (span) {
				const newVal = parseInt(span.textContent || '0', 10) + (action.classList.contains('is-active') ? (wasActive ? 0 : 1) : (wasActive ? -1 : 0));
				span.textContent = Math.max(0, newVal);
				span.classList.remove('is-tick');
				void span.offsetWidth;
				span.classList.add('is-tick');
			}
		}

		if (act === 'save') {
			const saved = action.classList.contains('is-active');
			// Swap label
			const label = action.querySelector('.la-snap-action-count');
			if (label) label.textContent = saved ? 'Saved' : 'Save';
			// Fill the bookmark icon when active
			const path = action.querySelector('svg path');
			if (path) path.setAttribute('fill', saved ? 'currentColor' : 'none');
			// Mirror to localStorage so an offline "Saved" view can read fast
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

	// ─── Duas sidebar — switch categories + per-day tick tracking ───
	const duasApp = document.querySelector('.la-app--duas-sidebar');
	if (duasApp) {
		const railButtons = duasApp.querySelectorAll('[data-cat]');
		const sections = duasApp.querySelectorAll('[data-cat-section]');
		const catIcon = duasApp.querySelector('[data-cat-icon]');
		const catTitle = duasApp.querySelector('[data-cat-title]');
		const catSub = duasApp.querySelector('[data-cat-sub]');
		const progressFill = duasApp.querySelector('[data-cat-progress-bar]');
		const progressText = duasApp.querySelector('[data-cat-progress-text]');
		let cats = {};
		try { cats = JSON.parse(duasApp.querySelector('#la-duas-cats').textContent); } catch (_) {}

		// Day-tick state — stored per identity in localStorage, keyed by YYYY-MM-DD
		// so it resets at midnight local time. Survives page reloads, navigations,
		// and works the same for anon visitors (no server roundtrip needed).
		const todayKey = () => {
			const d = new Date();
			return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
		};
		const stateKey = 'la_dua_ticks_' + todayKey();

		function loadTicks() {
			try { return new Set(JSON.parse(localStorage.getItem(stateKey) || '[]').map(String)); } catch (_) { return new Set(); }
		}
		function saveTicks(set) {
			try {
				localStorage.setItem(stateKey, JSON.stringify([...set]));
				// Prune old day keys to keep localStorage tidy
				Object.keys(localStorage).filter(k => k.startsWith('la_dua_ticks_') && k !== stateKey).forEach(k => {
					try { localStorage.removeItem(k); } catch (_) {}
				});
			} catch (_) {}
		}

		// Apply persisted tick state to cards on render
		const ticks = loadTicks();
		duasApp.querySelectorAll('.la-dua').forEach(card => {
			if (ticks.has(card.dataset.duaId)) card.classList.add('is-ticked');
		});

		// Compute & paint progress for a given category
		function paintProgress(catKey) {
			const section = duasApp.querySelector(`[data-cat-section="${catKey}"]`);
			if (!section) return;
			const cards = section.querySelectorAll('.la-dua');
			const total = cards.length;
			const done = [...cards].filter(c => c.classList.contains('is-ticked')).length;
			const pct = total ? Math.round((done / total) * 100) : 0;

			if (progressFill) {
				progressFill.style.width = pct + '%';
				progressFill.classList.toggle('is-complete', done === total && total > 0);
			}
			if (progressText) {
				progressText.textContent = `${done} of ${total} read today`;
			}

			// Mark rail count when complete
			const railBtn = duasApp.querySelector(`.la-duas-rail-btn[data-cat="${catKey}"]`);
			if (railBtn) railBtn.classList.toggle('is-complete', done === total && total > 0);
		}

		// Activate a category — swap section visibility, header, progress
		function activate(key) {
			railButtons.forEach(b => b.classList.toggle('is-active', b.dataset.cat === key));
			sections.forEach(s => s.classList.toggle('is-active', s.dataset.catSection === key));
			const meta = cats[key];
			if (meta) {
				if (catIcon) catIcon.textContent = meta.emoji;
				if (catTitle) catTitle.textContent = meta.label;
				if (catSub) catSub.textContent = meta.sub;
			}
			paintProgress(key);
			const pane = duasApp.querySelector('.la-duas-pane');
			pane?.scrollTo({ top: 0, behavior: 'smooth' });
		}

		railButtons.forEach(btn => {
			btn.addEventListener('click', () => {
				activate(btn.dataset.cat);
				if (navigator.vibrate) navigator.vibrate(10);
			});
		});

		// Paint progress for ALL categories on load so rail badges reflect completion
		Object.keys(cats).forEach(k => paintProgress(k));
		// Re-paint the currently-active category so its bar shows up
		const firstActive = duasApp.querySelector('.la-duas-rail-btn.is-active');
		if (firstActive) paintProgress(firstActive.dataset.cat);

		// Tick button per card — toggles today's "read" state.
		// On tick (not untick), AUTO-ADVANCE to the next dua so the snap
		// feed feels like a Duolingo lesson: complete this one → next.
		duasApp.addEventListener('click', (e) => {
			const tickBtn = e.target.closest('[data-action="tick-day"]');
			if (tickBtn) {
				e.stopPropagation();
				const card = tickBtn.closest('.la-dua');
				const id = String(tickBtn.dataset.id);
				const cat = tickBtn.dataset.cat;
				const t = loadTicks();
				const wasTicked = t.has(id);
				if (wasTicked) {
					t.delete(id);
					card?.classList.remove('is-ticked');
				} else {
					t.add(id);
					card?.classList.add('is-ticked');
					tickBtn.classList.remove('is-just-ticked');
					void tickBtn.offsetWidth;
					tickBtn.classList.add('is-just-ticked');
				}
				saveTicks(t);
				paintProgress(cat);
				if (navigator.vibrate) navigator.vibrate(12);

				// AUTO-ADVANCE — only on fresh tick (not on untick)
				if (!wasTicked && card) {
					const nextCard = card.nextElementSibling;
					if (nextCard && nextCard.classList.contains('la-dua')) {
						// Brief pause so the user sees the green "Read · next →"
						// flash before the next card snaps in
						setTimeout(() => {
							nextCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
						}, 600);
					}
				}
				return;
			}

			// Reset-day button
			const resetBtn = e.target.closest('[data-action="reset-day"]');
			if (resetBtn) {
				const active = duasApp.querySelector('.la-duas-rail-btn.is-active')?.dataset.cat;
				if (!active) return;
				if (!confirm("Clear today's ticks in this category?")) return;
				const section = duasApp.querySelector(`[data-cat-section="${active}"]`);
				const t = loadTicks();
				section?.querySelectorAll('.la-dua').forEach(c => {
					t.delete(String(c.dataset.duaId));
					c.classList.remove('is-ticked');
				});
				saveTicks(t);
				paintProgress(active);
				if (navigator.vibrate) navigator.vibrate(20);
			}
		});
	}

	// ─── New duas layout — Ameen / Copy / Share buttons ───
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('.la-dua-btn');
		if (!btn) return;
		const card = btn.closest('.la-dua');
		if (!card) return;
		const action = btn.dataset.action;
		const id = btn.dataset.id;
		if (!id) return;

		if (action === 'ameen') {
			const was = btn.classList.contains('is-active');
			btn.classList.toggle('is-active', !was);
			btn.setAttribute('aria-pressed', String(!was));
			btn.classList.remove('is-popping'); void btn.offsetWidth; btn.classList.add('is-popping');
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
				btn.classList.toggle('is-active', was);
				btn.setAttribute('aria-pressed', String(was));
			}
			return;
		}

		// Copy / Share — assemble dua text
		const arabic = card.querySelector('.la-dua-arabic')?.textContent.trim() || '';
		const translit = card.querySelector('.la-dua-translit')?.textContent.trim() || '';
		const meaning = card.querySelector('.la-dua-meaning')?.textContent.trim() || '';
		const source = card.querySelector('.la-dua-source')?.textContent.trim() || '';
		const title = card.querySelector('.la-dua-title')?.textContent.trim() || '';
		const txt = [title, arabic, translit, meaning, source && '— ' + source].filter(Boolean).join('\n\n');

		if (action === 'save') {
			try {
				await navigator.clipboard.writeText(txt);
				btn.classList.add('is-active');
				const lbl = btn.querySelector('span');
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
				try { await navigator.clipboard.writeText(txt); btn.querySelector('span').textContent = 'Copied'; } catch (_) {}
			}
		}
	});

	// ─── Legacy snap-feed Ameen handler (kept for back-compat if old layout reappears) ───
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

	// ─── Heart-Polish dhikr meditation (only on /dhikr v0.8+) ───
	const dhikrApp = document.querySelector('[data-dhikr-app]');
	if (dhikrApp) initDhikrMeditation(dhikrApp);

	function initDhikrMeditation(root) {
		const cfgEl = root.querySelector('#la-dhikr-config');
		if (!cfgEl) return;
		let config = {};
		try { config = JSON.parse(cfgEl.textContent); } catch (_) { return; }
		const phrases = config.phrases || [];
		const wisdom = config.wisdom || [];
		if (!phrases.length) return;

		// Scenes
		const scenes = {
			landing:  root.querySelector('[data-dhikr-scene="landing"]'),
			session:  root.querySelector('[data-dhikr-scene="session"]'),
			complete: root.querySelector('[data-dhikr-scene="complete"]'),
		};

		// Landing controls
		const phraseList = root.querySelector('[data-phrase-list]');
		const durationList = root.querySelector('[data-duration-list]');
		const modeList = root.querySelector('[data-mode-list]');
		const beginBtn = root.querySelector('[data-action="begin-dhikr"]');

		// Session UI refs
		const sessionPhrase = root.querySelector('[data-active-phrase]');
		const sessionTimer  = root.querySelector('[data-active-timer]');
		const breathArabic  = root.querySelector('[data-breath-arabic]');
		const breathTranslit = root.querySelector('[data-breath-translit]');
		const breathPhaseEl = root.querySelector('[data-breath-phase]');
		const breathCue     = root.querySelector('[data-breath-cue]');
		const breathMeaning = root.querySelector('[data-breath-meaning]');
		const breathCountEl = root.querySelector('[data-breath-count-num]');
		const heartPrompt   = root.querySelector('[data-heart-prompt]');
		const subsEl        = root.querySelector('[data-dhikr-subs]');
		const countList     = root.querySelector('[data-count-list]');
		const breathCircle  = root.querySelector('.la-breath-circle');
		// breathSplash removed in Wave 23 — water-cascade visual was poor execution.
		const breathGlow    = root.querySelector('.la-breath-glow');
		const bgYtHost      = root.querySelector('[data-bg-yt]');
		const psycheEl      = root.querySelector('[data-dhikr-psyche]');
		const rhythmValue   = root.querySelector('[data-rhythm-value]');
		const rhythmControl = root.querySelector('[data-rhythm-control]');
		const progressFill  = root.querySelector('[data-progress-fill]');

		const sceneList   = root.querySelector('[data-scene-list]');

		// Scene → YouTube video map (read from data attrs in landing)
		const sceneVideoIds = {};
		sceneList?.querySelectorAll('[data-scene]').forEach(b => {
			sceneVideoIds[b.getAttribute('data-scene')] = b.getAttribute('data-scene-video') || '';
		});

		// Web Audio API — rich, reverberant synthesis. No asset downloads,
		// works offline, no licensing concerns. Three layers (chant / duff
		// / breath) all run through a synthetic hall-reverb convolver so
		// they sit in a single resonant space — like recitation echoing
		// inside a domed masjid.
		const audioCtx = (window.AudioContext || window.webkitAudioContext) ? new (window.AudioContext || window.webkitAudioContext)() : null;
		let masterGain = null, reverbBus = null, dryBus = null;
		const audioLayers = { chant: null, duff: null, breath: null, mind: null };

		function ensureAudio() {
			if (!audioCtx) return;
			if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
			if (!masterGain) {
				masterGain = audioCtx.createGain();
				masterGain.gain.value = 0.8;
				masterGain.connect(audioCtx.destination);

				// Synthetic hall-reverb impulse response. White noise decays
				// exponentially over ~4 seconds — gives every sound a soft
				// tail, like sound bouncing inside a stone dome.
				const sr = audioCtx.sampleRate;
				const len = sr * 4;
				const irBuf = audioCtx.createBuffer(2, len, sr);
				for (let ch = 0; ch < 2; ch++) {
					const d = irBuf.getChannelData(ch);
					for (let i = 0; i < len; i++) {
						const t = i / sr;
						d[i] = (Math.random() * 2 - 1) * Math.pow(1 - t / 4, 3.2) * 0.4;
					}
				}
				const convolver = audioCtx.createConvolver();
				convolver.buffer = irBuf;
				reverbBus = audioCtx.createGain();
				reverbBus.gain.value = 0.55;
				dryBus = audioCtx.createGain();
				dryBus.gain.value = 1.0;
				reverbBus.connect(convolver).connect(masterGain);
				dryBus.connect(masterGain);
			}
		}

		// Helper: route a source through both dry + reverb buses (wet+dry mix)
		function routeAmbient(node, wet = 0.55) {
			const dryS = audioCtx.createGain(); dryS.gain.value = 1 - wet;
			const wetS = audioCtx.createGain(); wetS.gain.value = wet;
			node.connect(dryS).connect(dryBus);
			node.connect(wetS).connect(reverbBus);
		}

		// CHANT — Tanpura-style drone. Root (A2=110Hz) + fifth (E3) + octave
		// (A3) + slight detune for chorus warmth. Each oscillator has its
		// own slow LFO so the drone breathes naturally. Sounds like a real
		// reciter holding the phrase under you, not a sine wave.
		function buildChant() {
			if (!audioCtx) return null;
			const gain = audioCtx.createGain();
			gain.gain.value = 0;

			// Three voices: root, fifth, octave. Each pair detuned ±3 cents
			// for a subtle chorus effect (warmer than a pure sine).
			const voices = [];
			const freqs = [110, 110.2, 164.81, 164.95, 220, 220.3];
			const weights = [0.5, 0.5, 0.35, 0.35, 0.25, 0.25];
			freqs.forEach((freq, i) => {
				const osc = audioCtx.createOscillator();
				osc.type = i < 2 ? 'sine' : 'triangle';  // octaves use triangle for a brighter overtone
				osc.frequency.value = freq;
				const oscGain = audioCtx.createGain();
				oscGain.gain.value = weights[i] * 0.18;
				osc.connect(oscGain).connect(gain);
				voices.push(osc);
				osc.start();
			});

			// Slow tremolo on the whole drone — 7-8 second cycle, like breathing
			const lfo = audioCtx.createOscillator();
			lfo.type = 'sine';
			lfo.frequency.value = 0.13;
			const lfoGain = audioCtx.createGain();
			lfoGain.gain.value = 0.05;
			lfo.connect(lfoGain).connect(gain.gain);
			lfo.start();

			// A gentle low-pass to soften the high overtones
			const filt = audioCtx.createBiquadFilter();
			filt.type = 'lowpass';
			filt.frequency.value = 1800;
			filt.Q.value = 0.7;
			gain.connect(filt);
			routeAmbient(filt, 0.65);

			return {
				on() {
					const now = audioCtx.currentTime;
					gain.gain.cancelScheduledValues(now);
					gain.gain.linearRampToValueAtTime(0.45, now + 2.0);
				},
				off() {
					const now = audioCtx.currentTime;
					gain.gain.cancelScheduledValues(now);
					gain.gain.linearRampToValueAtTime(0, now + 1.2);
				},
				_destroy() { try { voices.forEach(v => v.stop()); lfo.stop(); } catch (_) {} },
			};
		}

		// DUFF — frame-drum hit. Two-part synthesis: a low THUMP (60Hz body)
		// + a brighter SLAP (filtered noise 'skin' transient). Each with its
		// own envelope. Then through reverb for that cavernous masjid sound.
		function makeDuffHit(when) {
			if (!audioCtx) return;
			const sr = audioCtx.sampleRate;
			const len = sr * 0.45;
			const buf = audioCtx.createBuffer(2, len, sr);
			for (let ch = 0; ch < 2; ch++) {
				const d = buf.getChannelData(ch);
				for (let i = 0; i < len; i++) {
					const t = i / sr;
					// Low thump: 60Hz sine with fast-decay envelope
					const thump = Math.sin(2 * Math.PI * 60 * t) * Math.exp(-t * 6) * 0.9;
					// Bright skin slap: filtered noise, very fast decay
					const slap = (Math.random() * 2 - 1) * Math.exp(-t * 30) * 0.45;
					// Subtle high frequency sparkle
					const sparkle = (Math.random() * 2 - 1) * Math.exp(-t * 50) * 0.12;
					d[i] = thump + slap + sparkle;
				}
			}
			const src = audioCtx.createBufferSource();
			src.buffer = buf;
			const filt = audioCtx.createBiquadFilter();
			filt.type = 'lowpass';
			filt.frequency.value = 1100;
			filt.Q.value = 1.2;
			const gain = audioCtx.createGain();
			gain.gain.value = 0.70;
			src.connect(filt).connect(gain);
			routeAmbient(gain, 0.5);
			src.start(when);
		}

		// MIND — binaural theta beats. LEFT ear sine 110 Hz, RIGHT ear sine
		// 116 Hz. The 6 Hz difference is interpreted by the brainstem as a
		// phantom carrier in the THETA band (4-7 Hz) — the EEG signature
		// of deep meditation, mystical states, and the experiences Newberg's
		// neurotheology team measured in Sufi + Tibetan monk practitioners.
		// Each channel goes to its dedicated output via ChannelMerger — the
		// binaural illusion only works when the two tones are spatially
		// separated, i.e. via HEADPHONES. Speakers blur them together.
		function buildMind() {
			if (!audioCtx) return null;
			const merger = audioCtx.createChannelMerger(2);
			const leftGain = audioCtx.createGain();
			const rightGain = audioCtx.createGain();
			leftGain.gain.value = 0;
			rightGain.gain.value = 0;

			const leftOsc = audioCtx.createOscillator();
			leftOsc.type = 'sine';
			leftOsc.frequency.value = 110;   // base carrier
			const rightOsc = audioCtx.createOscillator();
			rightOsc.type = 'sine';
			rightOsc.frequency.value = 116;  // +6 Hz = theta-band phantom

			// Slow LFO modulates volume gently so it doesn't feel mechanical
			const lfo = audioCtx.createOscillator();
			lfo.type = 'sine';
			lfo.frequency.value = 0.10;
			const lfoGain = audioCtx.createGain();
			lfoGain.gain.value = 0.015;
			lfo.connect(lfoGain);
			lfoGain.connect(leftGain.gain);
			lfoGain.connect(rightGain.gain);

			leftOsc.connect(leftGain).connect(merger, 0, 0);   // left channel only
			rightOsc.connect(rightGain).connect(merger, 0, 1); // right channel only
			merger.connect(masterGain);

			leftOsc.start(); rightOsc.start(); lfo.start();

			return {
				on() {
					if (!audioCtx) return;
					const now = audioCtx.currentTime;
					leftGain.gain.cancelScheduledValues(now);
					rightGain.gain.cancelScheduledValues(now);
					// Bring up slowly so the binaural entrainment builds gently
					leftGain.gain.linearRampToValueAtTime(0.085, now + 4);
					rightGain.gain.linearRampToValueAtTime(0.085, now + 4);
				},
				off() {
					if (!audioCtx) return;
					const now = audioCtx.currentTime;
					leftGain.gain.cancelScheduledValues(now);
					rightGain.gain.cancelScheduledValues(now);
					leftGain.gain.linearRampToValueAtTime(0, now + 1.5);
					rightGain.gain.linearRampToValueAtTime(0, now + 1.5);
				},
				_destroy() { try { leftOsc.stop(); rightOsc.stop(); lfo.stop(); } catch (_) {} },
			};
		}

		// BREATH — humanised breath pad. Pink noise through a band-pass
		// filter that sweeps with the breath phase (low formant on exhale,
		// higher formant on inhale — mimics opening/closing of the throat).
		// Subtle reverb gives it a roomy, present quality.
		function buildBreath() {
			if (!audioCtx) return null;
			const sr = audioCtx.sampleRate;
			const buf = audioCtx.createBuffer(2, sr * 2, sr);
			for (let ch = 0; ch < 2; ch++) {
				const d = buf.getChannelData(ch);
				// Generate pink-ish noise (Voss-McCartney approximation)
				let last = 0;
				for (let i = 0; i < d.length; i++) {
					last = last * 0.97 + (Math.random() * 2 - 1) * 0.03;
					d[i] = last * 5;
				}
			}
			const src = audioCtx.createBufferSource();
			src.buffer = buf;
			src.loop = true;
			// Band-pass filter to give it a vocal-tract character
			const filt = audioCtx.createBiquadFilter();
			filt.type = 'bandpass';
			filt.frequency.value = 700;
			filt.Q.value = 1.5;
			const gain = audioCtx.createGain();
			gain.gain.value = 0;
			src.connect(filt).connect(gain);
			routeAmbient(gain, 0.4);
			src.start();
			return {
				on(phase, halfDur) {
					if (!audioCtx) return;
					const now = audioCtx.currentTime;
					gain.gain.cancelScheduledValues(now);
					filt.frequency.cancelScheduledValues(now);
					if (phase === 'inhale') {
						// Inhale: filter sweeps UP (open throat) + gain swells
						gain.gain.linearRampToValueAtTime(0.16, now + halfDur * 0.55);
						gain.gain.linearRampToValueAtTime(0.10, now + halfDur);
						filt.frequency.linearRampToValueAtTime(1100, now + halfDur);
					} else {
						// Exhale: filter sweeps DOWN (close throat) + gain fades
						gain.gain.linearRampToValueAtTime(0.13, now + halfDur * 0.45);
						gain.gain.linearRampToValueAtTime(0.00, now + halfDur);
						filt.frequency.linearRampToValueAtTime(500, now + halfDur);
					}
				},
				off() { if (!audioCtx) return; gain.gain.cancelScheduledValues(audioCtx.currentTime); gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 0.5); },
				_destroy() { try { src.stop(); } catch (_) {} },
			};
		}

		let selected = {
			phrase: phrases[0],
			// Sunnah-prescribed count. duration in MINUTES is derived live
			// from count × the phrase's breath_s — see startSession().
			count: (phrases[0]?.counts && phrases[0].counts[0]) || 33,
			mode: 'qalbi',
			// Scene drives BOTH visual AND audio. Pick a scene = pick a
			// matching soundscape. Cosmos won't play ocean noise.
			scene: 'cosmos',
		};

		// Restore persisted scene + soundscape so the user's last choice carries over
		try {
			const saved = JSON.parse(localStorage.getItem('la_dhikr_prefs') || '{}');
			if (saved.scene) {
				selected.scene = saved.scene;
				root.querySelectorAll('[data-scene]').forEach(b => {
					const on = b.getAttribute('data-scene') === saved.scene;
					b.classList.toggle('is-selected', on);
					b.setAttribute('aria-checked', on ? 'true' : 'false');
				});
			}
		} catch (_) {}

		function persistPrefs() {
			try { localStorage.setItem('la_dhikr_prefs', JSON.stringify({ scene: selected.scene })); } catch (_) {}
		}

		// Radio-group click handler (delegated)
		function bindRadio(list, attr, onChange) {
			list?.addEventListener('click', (e) => {
				const btn = e.target.closest('[' + attr + ']');
				if (!btn) return;
				list.querySelectorAll('[' + attr + ']').forEach(b => {
					b.classList.toggle('is-selected', b === btn);
					b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
				});
				onChange(btn);
				if (navigator.vibrate) navigator.vibrate(10);
			});
		}
		bindRadio(phraseList, 'data-phrase-key', (btn) => {
			try { selected.phrase = JSON.parse(btn.getAttribute('data-phrase')); } catch (_) {}
			rebuildCountRow();  // different phrases have different Sunnah counts
			updateHero();        // live preview at top of landing
			updateBeginMeta();   // sticky CTA shows new count/duration
		});
		bindRadio(durationList, 'data-duration', (btn) => {
			selected.duration = parseInt(btn.getAttribute('data-duration'), 10);
		});
		// Count radio (Sunnah reps). Rebuild handler runs whenever phrase changes.
		function rebuildCountRow() {
			if (!countList || !selected.phrase?.counts) return;
			const counts = selected.phrase.counts;
			const breath = selected.phrase.breath_s || 10;
			countList.innerHTML = counts.map((c, i) => {
				const mins = Math.max(1, Math.round(c * breath / 60));
				const sel = i === 0;
				return `<button type="button" class="la-dhikr-count-pill${sel ? ' is-selected' : ''}" role="radio" aria-checked="${sel ? 'true' : 'false'}" data-count="${c}"><strong>${c}×</strong><span>~${mins} min</span></button>`;
			}).join('');
			selected.count = counts[0];
		}
		countList?.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-count]');
			if (!btn) return;
			countList.querySelectorAll('[data-count]').forEach(b => {
				b.classList.toggle('is-selected', b === btn);
				b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
			});
			selected.count = parseInt(btn.getAttribute('data-count'), 10);
			updateBeginMeta();
			if (navigator.vibrate) navigator.vibrate(10);
		});

		// HERO live-preview — runs on phrase change to mirror selected.phrase
		const heroArabic   = root.querySelector('[data-hero-arabic]');
		const heroTranslit = root.querySelector('[data-hero-translit]');
		const heroMeaning  = root.querySelector('[data-hero-meaning]');
		const heroNote     = root.querySelector('[data-hero-note]');
		const beginMeta    = root.querySelector('[data-begin-meta]');
		function updateHero() {
			const p = selected.phrase;
			if (!p) return;
			if (heroArabic)   heroArabic.textContent   = p.arabic || '';
			if (heroTranslit) heroTranslit.textContent = p.translit || '';
			if (heroMeaning)  heroMeaning.textContent  = p.meaning || '';
			if (heroNote)     heroNote.textContent     = p.note || '';
		}
		function updateBeginMeta() {
			if (!beginMeta || !selected.phrase) return;
			const mins = Math.max(1, Math.round((selected.count || 33) * (selected.phrase.breath_s || 10) / 60));
			beginMeta.textContent = (selected.count || 33) + '× · ~' + mins + ' min';
		}
		updateHero();
		updateBeginMeta();
		bindRadio(modeList, 'data-mode', (btn) => {
			selected.mode = btn.getAttribute('data-mode');
		});
		// Scene = single-select (radio), apply class immediately to preview backdrop
		bindRadio(sceneList, 'data-scene', (btn) => {
			selected.scene = btn.getAttribute('data-scene');
			applyScene(selected.scene);
			persistPrefs();
		});
		// Soundscape radio removed Wave 21 — scene picker covers audio too.

		function applyScene(sceneKey) {
			['cosmos','desert','forest','ocean','kaaba','none']
				.forEach(s => root.classList.remove('scene-' + s));
			root.classList.add('scene-' + (sceneKey || 'cosmos'));
		}
		// Apply on first paint
		applyScene(selected.scene);

		// Begin session
		beginBtn?.addEventListener('click', () => {
			startSession();
		});

		// Session state
		let sessionTimer_handle = null;
		let breathTimer_handle = null;
		let heartPromptTimer_handle = null;
		let endsAt = 0;
		let breathPhase = 'inhale'; // inhale | exhale
		let breathCycleIndex = 0;

		// HEART-DRAWING COACHING — a PROGRESSIVE arc, not random rotation.
		// Each stage maps to a phase of a typical contemplative session
		// (settle → focus → deepen → expand → integrate). Research on
		// mindfulness sessions (Lutz et al. 2008; Hölzel et al. 2011)
		// shows structured progression deepens immersion vs random cues.
		// We mix in Quran/Hadith/Sufi-master quotes throughout but the
		// SEQUENCE leads the heart through the journey.
		const heartCoachArc = {
			// STAGE 1 (first ~20% of session) — settle the body + breath
			settle: [
				"Soften your shoulders. Let your jaw rest. Plant your feet.",
				"Lengthen the spine. Crown of the head reaches up. Sit like a king before The King.",
				"Bring your attention to the heart, two fingers beneath the centre of your chest.",
				"Let the breath slow. The dhikr enters with the inhale, the world leaves with the exhale.",
				"Notice the stillness between breaths. Allah is there.",
			],
			// STAGE 2 (~20-50%) — focus the heart
			focus: [
				"Imagine the Name descending into the heart with each breath, like rain into dry earth.",
				"Whoever loves a thing remembers it often. Let this be your love. — Imam al-Junayd",
				"For everything there is a polish, and the polish of the heart is the remembrance of Allah. — The Prophet ﷺ",
				"The heart rusts like iron. Its polish is His Name. — Ibn al-Qayyim",
				"When the mind wanders, return without scolding. The return itself is the dhikr.",
			],
			// STAGE 3 (~50-75%) — deepen presence
			deepen: [
				"You are not calling Him from afar. He is closer to you than your jugular vein. (Qur'an 50:16)",
				"He is with you wherever you are. (Qur'an 57:4) — feel it now, in this breath.",
				"You think you are the one calling Allah, but His call is in your call. — Rumi",
				"Persist in remembrance until the tongue is silent and the heart speaks. — Imam al-Ghazali",
				"Do not abandon the remembrance because you do not feel His presence. Your heedlessness OF His remembrance is worse than your heedlessness WITHIN it. — Ibn Ata'illah",
			],
			// STAGE 4 (~75-95%) — expand into awe
			expand: [
				"He is the First — before this breath. He is the Last — after this breath. (Qur'an 57:3)",
				"Every breath that leaves the body without remembrance is a death. Every breath drawn in His presence is a life. — Shaykh Ahmad al-Sirhindi",
				"Sincere dhikr is when the one remembering forgets themselves in the remembrance. — Imam al-Junayd",
				"Remember Me, I will remember you. (Qur'an 2:152)",
				"The polish needs no force. Only persistence.",
			],
			// STAGE 5 (final ~5%) — integrate + carry forward
			integrate: [
				"Take this presence with you. Let it touch the next conversation, the next step.",
				"Whatever you were before this breath, you are softer now. Trust that.",
				"The dhikr does not end when the session ends. It descends into the bones.",
			],
		};
		// Flatten to a sequence we'll walk through based on session progress %.
		// Each entry: { text, stage }
		function buildCoachSequence() {
			const stages = ['settle', 'focus', 'deepen', 'expand', 'integrate'];
			const ranges = [[0,0.2],[0.2,0.5],[0.5,0.75],[0.75,0.95],[0.95,1.0]];
			const out = [];
			stages.forEach((s, i) => {
				heartCoachArc[s].forEach((t, j) => {
					const within = (j + 0.5) / heartCoachArc[s].length;
					const [lo, hi] = ranges[i];
					out.push({ text: t, stage: s, at: lo + (hi - lo) * within });
				});
			});
			return out;
		}
		const coachSequence = buildCoachSequence();

		function showScene(name) {
			Object.entries(scenes).forEach(([k, el]) => {
				if (!el) return;
				el.hidden = (k !== name);
			});
		}

		// Live breath duration in seconds. Mutable: the session arc auto-ramps
		// this (settle → coherence → deepen → hold). Manual ± slider locks
		// rhythmMode to 'manual' and the arc stops touching it.
		let breathS = 8;
		let rhythmMode = 'auto';     // 'auto' | 'manual'
		let phraseBaseS = 10;        // captured from selected.phrase.breath_s at session start

		// SESSION ARC — a CONTINUOUS journey, no plateaus.
		// Returns a multiplier of the phrase's natural breath_s.
		// For kalimah (10s base) the journey goes:
		//   start (0%)   = 0.5× =  5s = 12 BPM (close to resting breath)
		//   mid (50%)    = ~1.0× = 10s =  6 BPM (HRV coherence)
		//   end (100%)   = 1.5× = 15s =  4 BPM (deep parasympathetic
		//                                       + mystical-experience band,
		//                                       Wahbeh et al. 2014)
		// Smoothstep curve (cosine ease) means the change is fastest in the
		// middle and gentler at the edges — never feels stuck, never jolts.
		function arcMultiplier(progress) {
			const min = 0.5, max = 1.5;
			// Smoothstep S-curve: 0→0, 0.5→0.5, 1→1, with zero gradient at edges
			const eased = 0.5 - Math.cos(Math.PI * Math.max(0, Math.min(1, progress))) / 2;
			return min + (max - min) * eased;
		}
		function arcStageLabel(progress) {
			// Labels track the actual breath rate the user is at, not arbitrary
			// percentages — so they FEEL aligned with the breath, not abstract.
			if (progress < 0.10) return 'entering';
			if (progress < 0.30) return 'descending';
			if (progress < 0.55) return 'coherence';
			if (progress < 0.80) return 'deepening';
			if (progress < 0.95) return 'opening';
			return 'union';
		}

		function startSession() {
			showScene('session');
			document.body.classList.add('is-dhikr-active');
			root.classList.remove('is-lisani', 'is-qalbi', 'is-sirri');
			root.classList.add('is-' + selected.mode);
			applyScene(selected.scene);

			// Clear any inline opacity from prior session so the mode-class
			// CSS rule (e.g. .is-sirri hides Arabic) wins on fresh start.
			if (breathArabic) breathArabic.style.opacity = '';

			// Background YouTube video — load only on session start so the
			// bandwidth hit happens once, not on every page view.
			loadBackgroundVideo(selected.scene);

			// Wave 21: scene backdrop now carries its own audio (waves on
			// ocean, birds in forest, etc.) — wired in loadBackgroundVideo().
			// No separate soundscape iframe.

			// Start at the arc's entry rate (0.5× phrase) — close to resting
			// breath so the user can follow comfortably from breath 1. The
			// arc then ramps continuously, never plateaus.
			rhythmMode = 'auto';
			phraseBaseS = selected.phrase.breath_s || 10;
			breathS = +(phraseBaseS * arcMultiplier(0)).toFixed(1);
			// SESSION DURATION is DERIVED from Sunnah count × the phrase's
			// natural breath_s. Time becomes the side-effect of completing
			// the count, not the goal. Session also ends early if the user
			// hits the count before the (slightly padded) timer expires.
			selected.duration = Math.max(1, Math.round(selected.count * phraseBaseS / 60));
			endsAt = Date.now() + (selected.duration * 60 * 1000);
			updateRhythmDisplay();
			applyBreathAnimDuration();

			// Initial state — start in inhale phase with the inhale-half
			// Arabic + transliteration on the orb (kalimah → "لَا إِلَهَ" / "Lā ilāha").
			// Will flip every half-breath via updateBreathPhase().
			breathArabic.textContent = selected.phrase.arabic_inhale || selected.phrase.arabic || '';
			if (breathTranslit) breathTranslit.textContent = selected.phrase.inhale || selected.phrase.translit || '';
			if (breathPhaseEl) breathPhaseEl.textContent = 'Inhale';
			sessionPhrase.textContent = selected.phrase.translit || '';
			breathMeaning.textContent = selected.phrase.meaning || '';

			// Timer — endsAt was set above before the rhythm display
			tickTimer();
			sessionTimer_handle = setInterval(tickTimer, 500);

			// Breath cycle — toggle inhale/exhale on half-breath cadence
			breathPhase = 'inhale';
			breathCycleIndex = 0;
			if (breathCountEl) breathCountEl.textContent = '0';
			updateBreathPhase();
			startBreathInterval();

			// Reset progressive coaching arc for this session
			shownCoachIdx.clear();

			// Heart coaching — rotates every ~15s, picks from progressive arc
			// (settle → focus → deepen → expand → integrate) based on session
			// progress %. Sirri (Secret) mode skips them — that station IS
			// pure presence, no words.
			if (selected.mode !== 'sirri') {
				rotateHeartPrompt();
				heartPromptTimer_handle = setInterval(rotateHeartPrompt, 15000);
			} else if (heartPrompt) {
				heartPrompt.textContent = '';
			}

			// Soft start haptic
			if (navigator.vibrate) navigator.vibrate([20, 60, 30, 60, 20]);
		}

		// Walk the coaching arc based on session progress (0.0 - 1.0). Picks
		// the next un-shown prompt whose `at` is closest to current progress.
		const shownCoachIdx = new Set();
		function rotateHeartPrompt() {
			if (!heartPrompt || selected.mode === 'sirri') return;
			const total = selected.duration * 60 * 1000;
			const elapsed = total - Math.max(0, endsAt - Date.now());
			const progress = Math.max(0, Math.min(1, elapsed / total));

			// Find the next prompt within our window that hasn't shown yet
			let pick = null;
			for (let i = 0; i < coachSequence.length; i++) {
				if (shownCoachIdx.has(i)) continue;
				if (coachSequence[i].at <= progress + 0.05) {
					pick = { entry: coachSequence[i], idx: i };
					break;
				}
			}
			// Fallback — if everything in window is shown, pull a wisdom quote
			let text;
			if (pick) {
				shownCoachIdx.add(pick.idx);
				text = pick.entry.text;
			} else if (wisdom.length) {
				const w = wisdom[Math.floor(Math.random() * wisdom.length)];
				text = '"' + w.quote + '" — ' + w.speaker;
			} else {
				return;
			}

			heartPrompt.classList.add('is-fading');
			setTimeout(() => {
				heartPrompt.textContent = text;
				heartPrompt.classList.remove('is-fading');
			}, 700);
		}

		function applyBreathAnimDuration() {
			// Orb scale is now JS-driven per-phase (in updateBreathPhase) —
			// no CSS animation duration to set. Psyche backdrop keeps its
			// keyframe-driven hue drift but tempo locks to breathS.
			if (psycheEl) psycheEl.style.animationDuration = (breathS * 1.75) + 's';
		}

		// Asymmetric breath cadence — 40% inhale / 60% exhale.
		// Research: longer exhale increases parasympathetic / vagal tone
		// (Russo et al. 2017; Sevoz-Couche & Laborde 2022). Net cycle =
		// breathS as before, but each half is scheduled separately by
		// chained setTimeout rather than a uniform setInterval.
		function startBreathInterval() {
			if (breathTimer_handle) { clearTimeout(breathTimer_handle); breathTimer_handle = null; }
			function nextHalf() {
				breathPhase = (breathPhase === 'inhale') ? 'exhale' : 'inhale';
				if (breathPhase === 'inhale') breathCycleIndex++;
				updateBreathPhase();
				const dur = (breathPhase === 'inhale' ? 0.4 : 0.6) * breathS * 1000;
				breathTimer_handle = setTimeout(nextHalf, dur);
			}
			// First half = remainder of current phase (inhale lasts 40% of breathS)
			const firstDur = (breathPhase === 'inhale' ? 0.4 : 0.6) * breathS * 1000;
			breathTimer_handle = setTimeout(nextHalf, firstDur);
		}

		// CHANT — inject a hidden YouTube iframe playing a real qari recording.
		// Uses the YT IFrame API + explicit ENDED-state listener to FORCE
		// replay the same video instead of letting YouTube drift to its
		// recommended (which once dropped Rick Astley into a meditation —
		// not the kind of transcendence we promised).
		const chantYtPlayers = { chant: null };
		function loadChantVideo(videoId) {
			if (!chantHost) return;
			// Kill any existing player
			if (chantYtPlayers.chant) {
				try { chantYtPlayers.chant.destroy(); } catch (_) {}
				chantYtPlayers.chant = null;
			}
			chantHost.innerHTML = '';
			if (!videoId) return;
			// Create a placeholder div; YT API replaces it with an iframe
			const placeholder = document.createElement('div');
			placeholder.id = 'la-chant-yt-' + Date.now();
			chantHost.appendChild(placeholder);
			// Ensure YT API is loaded, then build the player
			ensureYTApi(() => {
				try {
					chantYtPlayers.chant = new YT.Player(placeholder.id, {
						videoId: videoId,
						host: 'https://www.youtube-nocookie.com',
						playerVars: {
							autoplay: 1, mute: 0, loop: 1, playlist: videoId,
							controls: 0, modestbranding: 1, playsinline: 1, rel: 0,
							iv_load_policy: 3, cc_load_policy: 0, disablekb: 1, fs: 0,
						},
						events: {
							onReady: (e) => {
								try {
									// Chant is BACKGROUND — user's own dhikr (visual cues
									// driving silent inner repetition) is the focus. Set
									// the YT volume to ~28% so the qari/halaqa feels
									// like it's drifting from the next room, not leading.
									e.target.setVolume(28);
									e.target.playVideo();
								} catch (_) {}
							},
							onStateChange: (e) => {
								// YT.PlayerState.ENDED === 0
								// loop=1 should re-trigger, but in practice it occasionally
								// fails AND shows the dreaded "Up Next" overlay.
								// Force-replay here so nothing but our video ever plays.
								if (e.data === 0) {
									try { e.target.seekTo(0, true); e.target.playVideo(); } catch (_) {}
								}
							},
							onError: (e) => {
								// Video unavailable / blocked etc — just unload, fall back
								// to the CSS visual + Web Audio layers.
								try { e.target.destroy(); } catch (_) {}
								chantYtPlayers.chant = null;
							},
						},
					});
				} catch (_) {}
			});
		}

		// Lazy-load YT IFrame API exactly once across the page
		let _ytApiPromise = null;
		function ensureYTApi(cb) {
			if (window.YT && window.YT.Player) return cb();
			if (_ytApiPromise) { _ytApiPromise.then(cb); return; }
			_ytApiPromise = new Promise(resolve => {
				const prev = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = function () {
					if (typeof prev === 'function') try { prev(); } catch (_) {}
					resolve();
				};
				const s = document.createElement('script');
				s.src = 'https://www.youtube.com/iframe_api';
				s.async = true;
				document.head.appendChild(s);
			});
			_ytApiPromise.then(cb);
		}

		// Scene backdrop uses the YT API so we can catch ENDED + drift,
		// force-replay our scene video, never letting recommended videos
		// (Rick Astley etc) surface for any reason.
		let bgYtPlayer = null;
		let bgYtWatchdog = null;
		let bgYtTargetVideoId = '';
		// SCENE_START_SECONDS — skip past each video's intro/title card.
		// 30 seconds works for most ambient videos; tune per video later
		// if any specific one needs more.
		const SCENE_START_SECONDS = 30;
		function loadBackgroundVideo(sceneKey) {
			if (!bgYtHost) return;
			if (bgYtPlayer) { try { bgYtPlayer.destroy(); } catch (_) {} bgYtPlayer = null; }
			if (bgYtWatchdog) { clearInterval(bgYtWatchdog); bgYtWatchdog = null; }
			bgYtHost.innerHTML = '';
			bgYtHost.classList.remove('is-playing');
			const vid = sceneVideoIds[sceneKey];
			bgYtTargetVideoId = vid || '';
			if (!vid) return;
			const placeholder = document.createElement('div');
			placeholder.id = 'la-scene-yt-' + Date.now();
			bgYtHost.appendChild(placeholder);
			ensureYTApi(() => {
				try {
					bgYtPlayer = new YT.Player(placeholder.id, {
						videoId: vid,
						host: 'https://www.youtube-nocookie.com',
						playerVars: {
							// Wave 21: scene's own audio is the soundscape.
							// Wave 24: `start` skips the title-card/intro frames
							// so the video opens already inside the ambient.
							autoplay: 1, mute: 0, loop: 1, playlist: vid,
							controls: 0, modestbranding: 1, playsinline: 1, rel: 0,
							iv_load_policy: 3, cc_load_policy: 0, disablekb: 1, fs: 0,
							start: SCENE_START_SECONDS,
						},
						events: {
							onReady: (e) => {
								try {
									// Background ambient — never leading. 30% volume.
									e.target.setVolume(30);
									e.target.seekTo(SCENE_START_SECONDS, true);
									e.target.playVideo();
								} catch (_) {}
								bgYtHost.classList.add('is-playing');
							},
							onStateChange: (e) => {
								if (e.data === 0) {
									try {
										e.target.seekTo(SCENE_START_SECONDS, true);
										e.target.playVideo();
									} catch (_) {}
								}
							},
							onError: (e) => {
								try { e.target.destroy(); } catch (_) {}
								bgYtPlayer = null;
							},
						},
					});

					// WATCHDOG — poll EVERY 1.5s now (was 4s — Rick still snuck
					// in within that window on the Ocean video). Faster cadence
					// catches drift before the user even notices something
					// off-screen happened.
					if (bgYtWatchdog) clearInterval(bgYtWatchdog);
					bgYtWatchdog = setInterval(() => {
						if (!bgYtPlayer || !bgYtTargetVideoId) return;
						try {
							const data = bgYtPlayer.getVideoData?.();
							const currentId = data?.video_id;
							if (currentId && currentId !== bgYtTargetVideoId) {
								// Drift detected — force back to our scene video,
								// skipping past any intro frames.
								bgYtPlayer.loadVideoById({
									videoId: bgYtTargetVideoId,
									startSeconds: SCENE_START_SECONDS,
								});
							}
						} catch (_) {}
					}, 1500);
				} catch (_) {}
			});
		}

		function updateBreathPhase() {
			const p = selected.phrase;
			const cue = breathPhase === 'inhale' ? (p.inhale || 'Inhale') : (p.exhale || 'Exhale');
			const meaning = breathPhase === 'inhale'
				? (p.meaning_inhale || p.meaning || '')
				: (p.meaning_exhale || p.meaning || '');
			// ARABIC on the orb flips between the inhale + exhale halves so
			// the Arabic the user contemplates matches what their breath is
			// doing in real time (kalimah inhale = لَا إِلَهَ, exhale = إِلَّا ٱللَّٰه).
			const arabicHalf = breathPhase === 'inhale'
				? (p.arabic_inhale || p.arabic || '')
				: (p.arabic_exhale || p.arabic || '');

			breathCue.textContent = cue;
			if (breathMeaning) breathMeaning.textContent = meaning;
			if (breathArabic)  breathArabic.textContent  = arabicHalf;
			// Transliteration on the orb flips with the phase too
			if (breathTranslit) breathTranslit.textContent = cue;
			if (breathPhaseEl) {
				breathPhaseEl.textContent = breathPhase === 'inhale' ? 'Inhale' : 'Exhale';
				breathPhaseEl.classList.toggle('is-inhale', breathPhase === 'inhale');
				breathPhaseEl.classList.toggle('is-exhale', breathPhase === 'exhale');
			}

			// Drive subtitle colour shift
			subsEl?.classList.toggle('is-inhale', breathPhase === 'inhale');
			subsEl?.classList.toggle('is-exhale', breathPhase === 'exhale');

			// DRIVE THE ORB SCALE FROM JS — guarantees the size matches
			// the Inhale/Exhale text in real time. The transition duration
			// EXACTLY equals this phase's actual duration (40% inhale or
			// 60% exhale of breathS), so the orb finishes expanding right
			// at the moment the label flips to Exhale.
			const halfDurSec = (breathPhase === 'inhale' ? 0.4 : 0.6) * breathS;
			if (breathCircle) {
				breathCircle.style.transition = 'transform ' + halfDurSec + 's ' + (breathPhase === 'inhale' ? 'ease-out' : 'ease-in');
				breathCircle.style.transform = 'scale(' + (breathPhase === 'inhale' ? 1.08 : 0.92) + ')';
			}
			if (breathGlow) {
				breathGlow.style.transition = 'transform ' + halfDurSec + 's ease-in-out, opacity ' + halfDurSec + 's ease-in-out';
				breathGlow.style.transform = 'scale(' + (breathPhase === 'inhale' ? 1.10 : 0.95) + ')';
				breathGlow.style.opacity   = breathPhase === 'inhale' ? '0.95' : '0.55';
			}

			// Sirri (Secret) mode hides Arabic + the orb phase label
			if (selected.mode !== 'sirri') {
				breathArabic.style.opacity = '1';  // always full opacity for the active half
			}

			// (Audio scheduling removed in Wave 19 — silent practice.)
			// Increment breath count + paint it on each new INHALE.
			if (breathPhase === 'inhale') {
				if (breathCountEl) breathCountEl.textContent = String(breathCycleIndex);
				// Session ends as soon as the Sunnah count is reached. The
				// timer is just an estimate — the COUNT is what matters.
				if (selected.count && breathCycleIndex >= selected.count) {
					finishSession();
				}
			}

			// (Splash ring removed in Wave 23 — water-cascade visual scrapped.)
		}

		// Rhythm controls — ± buttons step breath_s by 1s, clamped [3, 18].
		// First manual press locks rhythmMode to 'manual' for the rest of
		// the session (user agency overrides the auto-ramp).
		rhythmControl?.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-rhythm]');
			if (!btn) return;
			const dir = btn.getAttribute('data-rhythm');
			let next = Math.round(breathS) + (dir === 'faster' ? -1 : 1);
			next = Math.max(3, Math.min(18, next));
			if (next === Math.round(breathS)) return;
			breathS = next;
			rhythmMode = 'manual';
			updateRhythmDisplay();
			applyBreathAnimDuration();
			startBreathInterval();
			if (navigator.vibrate) navigator.vibrate(10);
		});
		function updateRhythmDisplay() {
			if (!rhythmValue) return;
			// Show seconds + breaths-per-minute on line 1, arc-stage on line 2.
			// 6/min ≈ coherence frequency (Lehrer & Gevirtz 2014; gold-standard
			// for HRV + parasympathetic activation).
			const bpm = (60 / breathS).toFixed(1).replace(/\.0$/, '');
			let stage = '';
			if (rhythmMode === 'auto' && endsAt) {
				const total = selected.duration * 60 * 1000;
				const elapsed = total - Math.max(0, endsAt - Date.now());
				const pct = Math.max(0, Math.min(1, elapsed / total));
				stage = arcStageLabel(pct);
			} else if (rhythmMode === 'manual') {
				stage = 'manual';
			}
			const secs = breathS.toFixed(1).replace(/\.0$/, '');
			rhythmValue.innerHTML = '<span class="la-dhikr-rhythm-num">' + secs + 's · ' + bpm + '/min</span>'
				+ (stage ? '<span class="la-dhikr-rhythm-stage">' + stage + '</span>' : '');
			rhythmValue.classList.toggle('is-coherence', breathS >= 8 && breathS <= 13);
		}

		function destroyAudioLayers() {
			Object.entries(audioLayers).forEach(([k, layer]) => {
				if (!layer) return;
				try { layer.off(); layer._destroy && layer._destroy(); } catch (_) {}
				audioLayers[k] = null;
			});
		}

		function tickTimer() {
			const remaining = Math.max(0, endsAt - Date.now());
			const mins = Math.floor(remaining / 60000);
			const secs = Math.floor((remaining % 60000) / 1000);
			sessionTimer.textContent = mins + ':' + String(secs).padStart(2, '0');
			const total = selected.duration * 60 * 1000;
			const elapsed = total - remaining;
			const pct = Math.min(100, (elapsed / total) * 100);
			if (progressFill) progressFill.style.width = pct + '%';

			// Auto-ramp the breath rhythm along the session arc. The arc is
			// CONTINUOUS — we update breathS every tick so the journey feels
			// alive, never stuck. Threshold of 0.1s prevents flicker on the
			// rounded display while the underlying value drifts smoothly.
			if (rhythmMode === 'auto') {
				const target = +(phraseBaseS * arcMultiplier(pct / 100)).toFixed(1);
				if (Math.abs(target - breathS) >= 0.1) {
					breathS = target;
					updateRhythmDisplay();
					applyBreathAnimDuration();
					// The chained nextHalf() setTimeout reads breathS dynamically
					// on each call, so the new pace applies on the next phase
					// boundary (never jolts mid-inhale).
				}
			}

			if (remaining <= 0) finishSession();
		}

		// (Old rotating guidance text removed — the big subtitle now carries
		// the guidance role with inhale-cue + translation per breath.)

		function clearTimers() {
			clearInterval(sessionTimer_handle);
			clearTimeout(breathTimer_handle);      // setTimeout chain since 4:6 ratio
			clearInterval(heartPromptTimer_handle);
			sessionTimer_handle = breathTimer_handle = heartPromptTimer_handle = null;
		}

		function stopAudio() {
			// Tear down scene-backdrop iframe + the watchdog timer.
			if (bgYtWatchdog) { clearInterval(bgYtWatchdog); bgYtWatchdog = null; }
			if (bgYtPlayer) { try { bgYtPlayer.destroy(); } catch(_) {} bgYtPlayer = null; }
			if (bgYtHost)  { bgYtHost.innerHTML = ''; bgYtHost.classList.remove('is-playing'); }
		}

		function endSession() {
			clearTimers();
			stopAudio();
			document.body.classList.remove('is-dhikr-active');
			showScene('landing');
		}

		function finishSession() {
			clearTimers();
			stopAudio();
			showScene('complete');
			if (navigator.vibrate) navigator.vibrate([40, 80, 40]);
		}

		root.querySelector('[data-action="end-session"]')?.addEventListener('click', endSession);
		root.querySelector('[data-action="return-home"]')?.addEventListener('click', endSession);
		root.querySelector('[data-action="reset-session"]')?.addEventListener('click', () => {
			showScene('landing');
		});
	}

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

	// ─── Connect — skills marketplace filter + search + submit ───
	(function() {
		const grid = document.querySelector('[data-skills-grid]');
		const chips = document.querySelectorAll('[data-skill-chips] .la-chip');
		const search = document.querySelector('[data-skill-search]');
		const form = document.querySelector('[data-skill-form]');
		if (!grid && !form) return;

		let activeCat = 'all';
		let query = '';

		function applyFilter() {
			if (!grid) return;
			const q = query.trim().toLowerCase();
			const cards = grid.querySelectorAll('.la-skill');
			let visible = 0;
			cards.forEach(card => {
				const cat = card.dataset.cat;
				const text = card.dataset.q || '';
				const catOk = (activeCat === 'all' || cat === activeCat);
				const qOk = (!q || text.includes(q));
				const show = catOk && qOk;
				card.classList.toggle('is-hidden', !show);
				if (show) visible++;
			});
			const counter = document.querySelector('[data-listing-count]');
			if (counter) counter.textContent = visible;
		}

		chips.forEach(chip => chip.addEventListener('click', () => {
			chips.forEach(c => c.classList.toggle('is-active', c === chip));
			activeCat = chip.dataset.cat || 'all';
			applyFilter();
			if (navigator.vibrate) navigator.vibrate(8);
		}));

		search?.addEventListener('input', () => {
			query = search.value || '';
			applyFilter();
		});

		// Submit handler — POST to /skills/submit, show success or error
		form?.addEventListener('submit', async (e) => {
			e.preventDefault();
			const submitBtn = form.querySelector('.la-skill-form-submit');
			const msg = form.querySelector('[data-form-msg]');
			if (submitBtn) submitBtn.disabled = true;
			if (msg) { msg.hidden = true; msg.className = 'la-skill-form-msg'; }

			const data = Object.fromEntries(new FormData(form).entries());
			try {
				const r = await fetch(`${LA.apiRoot}skills/submit`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': LA.nonce,
						'X-LA-Session': LA.sessionId,
					},
					body: JSON.stringify(data),
				});
				const body = await r.json();
				if (!r.ok) throw new Error(body.message || 'submission failed');
				form.reset();
				if (msg) {
					msg.hidden = false;
					msg.classList.add('is-success');
					msg.textContent = 'JazakAllah khair — your listing is in review. We aim to approve within 24h.';
				}
				if (navigator.vibrate) navigator.vibrate([20, 60, 30]);
			} catch (err) {
				if (msg) {
					msg.hidden = false;
					msg.classList.add('is-error');
					msg.textContent = err.message || 'Something went wrong. Please try again.';
				}
			} finally {
				if (submitBtn) submitBtn.disabled = false;
			}
		});

		// Contact tap — increment counter (fire-and-forget; link still opens)
		document.addEventListener('click', (e) => {
			const a = e.target.closest('[data-action="skill-contact"]');
			if (!a) return;
			const id = a.dataset.id;
			if (!id) return;
			fetch(`${LA.apiRoot}skills/${id}/contact`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
				keepalive: true,
			}).catch(() => {});
		});
	})();

	// ─── Masjid event RSVP / favourite toggles ───
	// Delegated handler — works for all event posters in the rail.
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-action="event-rsvp"], [data-action="event-fav"]');
		if (!btn) return;
		const id = btn.dataset.id;
		const status = btn.dataset.action === 'event-rsvp' ? 'rsvp' : 'fav';
		if (!id) return;

		// Optimistic flip
		const wasActive = btn.classList.contains('is-active');
		btn.classList.toggle('is-active', !wasActive);
		btn.setAttribute('aria-pressed', String(!wasActive));
		if (status === 'fav') {
			btn.classList.remove('is-pop'); void btn.offsetWidth; btn.classList.add('is-pop');
			// Toggle SVG fill on the heart
			const path = btn.querySelector('svg path');
			if (path) path.setAttribute('fill', !wasActive ? 'currentColor' : 'none');
		}
		if (status === 'rsvp') {
			const lbl = btn.querySelector('.la-event-rsvp-on');
			if (lbl) lbl.textContent = !wasActive ? '✓ Going' : 'RSVP';
		}
		if (navigator.vibrate) navigator.vibrate(status === 'rsvp' ? [15, 30, 25] : 12);

		try {
			const r = await fetch(`${LA.apiRoot}events/${id}/${status}`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': LA.nonce, 'X-LA-Session': LA.sessionId },
			});
			const data = await r.json();
			if (typeof data.active === 'boolean') {
				btn.classList.toggle('is-active', data.active);
				btn.setAttribute('aria-pressed', String(data.active));
				if (status === 'rsvp') {
					const lbl = btn.querySelector('.la-event-rsvp-on');
					if (lbl) lbl.textContent = data.active ? '✓ Going' : 'RSVP';
					const count = btn.querySelector('[data-rsvp-count]');
					if (count && typeof data.count === 'number') count.textContent = data.count;
				}
				if (status === 'fav') {
					const path = btn.querySelector('svg path');
					if (path) path.setAttribute('fill', data.active ? 'currentColor' : 'none');
				}
			}
		} catch (_) {
			// Rollback
			btn.classList.toggle('is-active', wasActive);
			btn.setAttribute('aria-pressed', String(wasActive));
		}
	});
})();
