<?php
/**
 * PWA support — manifest + service worker route registration.
 *
 * Without an admin manifest plugin, we serve our own manifest.json + sw.js
 * from custom rewrite endpoints. This makes loveallah installable on Android
 * and iOS as a PWA, and is the foundation for the Android TWA build.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_PWA {

	public static function register() : void {
		add_action( 'init',          [ __CLASS__, 'rewrites' ] );
		add_filter( 'query_vars',    [ __CLASS__, 'query_vars' ] );
		add_action( 'parse_request', [ __CLASS__, 'serve_early' ] );
		add_action( 'template_redirect', [ __CLASS__, 'serve' ] );
		// Block WP's canonical redirect for our PWA endpoints (it adds trailing slash).
		add_filter( 'redirect_canonical', [ __CLASS__, 'no_canonical_for_pwa' ], 10, 2 );
		add_action( 'wp_head',       [ __CLASS__, 'head_tags' ], 1 );
		add_action( 'wp_footer',     [ __CLASS__, 'register_sw_script' ], 99 );
	}

	/**
	 * Serve PWA assets as early as possible — before WP's redirect_canonical fires.
	 */
	public static function serve_early( $wp ) : void {
		if ( empty( $wp->query_vars['la_pwa'] ) ) return;
		self::dispatch( $wp->query_vars['la_pwa'] );
	}

	/**
	 * Disable trailing-slash redirect for PWA paths.
	 */
	public static function no_canonical_for_pwa( $redirect_url, $requested_url ) {
		$path = wp_parse_url( $requested_url, PHP_URL_PATH );
		if ( ! $path ) return $redirect_url;
		if ( $path === '/manifest.json' || $path === '/sw.js' || $path === '/.well-known/assetlinks.json' ) {
			return false;
		}
		return $redirect_url;
	}

	public static function rewrites() : void {
		// /manifest.json
		add_rewrite_rule( '^manifest\.json$', 'index.php?la_pwa=manifest', 'top' );
		// /sw.js (service worker — must be served from root scope)
		add_rewrite_rule( '^sw\.js$',         'index.php?la_pwa=sw',       'top' );
		// /.well-known/assetlinks.json (for Android Digital Asset Links)
		add_rewrite_rule( '^\.well-known/assetlinks\.json$', 'index.php?la_pwa=assetlinks', 'top' );
		// Wave 86: /privacy and /terms (required by Google Play Store
		// store-listing form + Apple App Store privacy declaration).
		add_rewrite_rule( '^privacy/?$', 'index.php?la_pwa=privacy', 'top' );
		add_rewrite_rule( '^terms/?$',   'index.php?la_pwa=terms',   'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'la_pwa';
		return $vars;
	}

	public static function serve() : void {
		$type = get_query_var( 'la_pwa' );
		if ( ! $type ) return;
		self::dispatch( $type );
	}

	private static function dispatch( string $type ) : void {
		switch ( $type ) {
			case 'manifest':   self::send_manifest();   break;
			case 'sw':         self::send_sw();         break;
			case 'assetlinks': self::send_assetlinks(); break;
			case 'privacy':    self::send_legal( 'privacy' ); break;
			case 'terms':      self::send_legal( 'terms' );   break;
		}
	}

	/**
	 * Wave 86: minimal but compliant legal pages so Play Store + App Store
	 * accept the listing. Rendered inline rather than as wp_insert_post
	 * Pages so they don't depend on wp-admin and ship with each deploy.
	 */
	private static function send_legal( string $which ) : void {
		$is_privacy = ( $which === 'privacy' );
		$title = $is_privacy ? 'Privacy policy — Love Allah' : 'Terms of use — Love Allah';
		$body  = $is_privacy ? self::privacy_html() : self::terms_html();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<meta name="theme-color" content="#ED1C6C">
<style>
	* { box-sizing: border-box }
	body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif; max-width: 760px; margin: 0 auto; padding: 32px 20px 64px; line-height: 1.6; color: #2C1338; background: #FFF8FB; }
	h1 { font-size: 28px; font-weight: 900; color: #6B1846; margin-top: 0; }
	h2 { font-size: 18px; font-weight: 800; color: #6B1846; margin-top: 28px; }
	p, li { font-size: 15px; }
	a { color: #ED1C6C; }
	.last { font-size: 13px; color: #777; margin-top: 36px; padding-top: 18px; border-top: 1px solid #eee; }
	.brand { display: inline-block; padding: 6px 12px; background: #ED1C6C; color: #fff; border-radius: 999px; font-weight: 800; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; }
	.back { display: inline-block; margin-top: 28px; color: #6B1846; text-decoration: none; font-weight: 700; }
</style>
</head><body>
<a class="brand" href="/">Love Allah</a>
<h1><?php echo esc_html( $is_privacy ? 'Privacy Policy' : 'Terms of Use' ); ?></h1>
<?php echo $body; // already-trusted static markup ?>
<p class="last">Last updated: <?php echo esc_html( gmdate( 'F Y' ) ); ?> · Contact: <a href="mailto:hello@loveallah.app">hello@loveallah.app</a></p>
<a class="back" href="/">← Back to Love Allah</a>
</body></html>
		<?php
		exit;
	}

	private static function privacy_html() : string {
		ob_start(); ?>
<p>Love Allah ("we", "our", "us") is a sacred ritual app that helps Muslims draw closer to Allah ﷻ through prayer-times, daily dhikr, and a curated Islamic content feed. This policy explains what we collect, why, and what control you have.</p>

<h2>What we collect</h2>
<ul>
	<li><strong>Anonymous session ID</strong> — a random identifier stored in a cookie on first visit. Used to remember which dhikr you've completed today and which videos you've already seen so we don't show them again.</li>
	<li><strong>Email and phone (optional)</strong> — only if you choose to sign in to save your progress across devices. We never sell, share or rent these to anyone.</li>
	<li><strong>Approximate location (optional)</strong> — only if you tap "use my location" so we can compute accurate prayer times for your area. We do not store your raw GPS coordinates server-side; we compute the prayer times and discard the input.</li>
	<li><strong>App interactions</strong> — which videos you watch, save and share. Used to personalise your feed and improve content quality. Anonymous unless you've signed in.</li>
	<li><strong>Standard server logs</strong> — IP address (hashed), browser type, request times. Kept for 30 days for security and debugging.</li>
</ul>

<h2>What we do NOT collect</h2>
<ul>
	<li>Your contacts, photos, microphone or camera.</li>
	<li>Precise GPS coordinates (only the city-level latitude/longitude you grant for prayer times).</li>
	<li>Financial information of any kind. We do not process payments in the app.</li>
	<li>Behavioural advertising profiles. We do not run third-party ad networks.</li>
</ul>

<h2>Third-party services</h2>
<ul>
	<li><strong>YouTube</strong> — feed videos are embedded from YouTube. Watching one may transmit standard YouTube analytics to Google per their own policy.</li>
	<li><strong>Cloudflare</strong> — content delivery and DDoS protection. Sees your IP as any web request would.</li>
	<li><strong>Email infrastructure</strong> — if you sign in with email, we use Postmark to send transactional messages (e.g. weekly remembrance email if you opt in).</li>
</ul>

<h2>Your rights</h2>
<ul>
	<li>Request a copy of any personal data we hold on you.</li>
	<li>Request deletion of your account and all associated history.</li>
	<li>Withdraw consent for any optional data (location, email, weekly newsletter) at any time.</li>
</ul>
<p>Email <a href="mailto:hello@loveallah.app">hello@loveallah.app</a> with "data request" or "delete my account" in the subject and we will action it within 14 days.</p>

<h2>Data retention</h2>
<p>Anonymous session data is retained for up to 90 days for the seen-content de-duplication algorithm. Signed-in account data is retained until you request deletion. Server logs are kept for 30 days.</p>

<h2>Security</h2>
<p>All traffic is encrypted in transit over HTTPS. Personal data is stored on Cloudways managed infrastructure with industry-standard access controls. We do not store passwords (sign-in uses an email + phone passwordless flow).</p>

<h2>Children</h2>
<p>Love Allah is for ages 13 and above. We do not knowingly collect data from children under 13. If you believe a child has provided us with data, contact us and we will delete it.</p>

<h2>Changes</h2>
<p>If this policy changes materially, the app will show a notice and ask you to re-consent. The "Last updated" date below changes whenever we revise this page.</p>
		<?php return ob_get_clean();
	}

	private static function terms_html() : string {
		ob_start(); ?>
<p>By using Love Allah you agree to these terms. They are intentionally short.</p>

<h2>What Love Allah is</h2>
<p>A sacred ritual app whose purpose is to help Muslims draw closer to Allah ﷻ. We provide prayer times, daily dhikr, and a feed of curated Islamic content (lectures, reminders, qira'at, nasheeds). The app and its content are offered as-is for personal use.</p>

<h2>Acceptable use</h2>
<ul>
	<li>Use the app for personal spiritual benefit and respectful sharing within your community.</li>
	<li>Do not attempt to scrape, reverse-engineer, or redistribute the curated feed.</li>
	<li>Do not use the app for any unlawful purpose or to harass others.</li>
</ul>

<h2>Content</h2>
<p>Videos in the feed are embedded from third-party platforms (primarily YouTube). We do not own that content; we curate scholars whose channels we believe to be of benefit. If you are a content creator and would like your channel removed, email <a href="mailto:hello@loveallah.app">hello@loveallah.app</a> and we will action within 7 days.</p>

<h2>No warranty</h2>
<p>Prayer times are computed from open astronomical formulas (high-precision Fajr/Isha angles) and are accurate to within ±1 minute for most locations, but you should always confirm with your local masjid. Love Allah is not a substitute for scholarly verification of religious rulings.</p>

<h2>Liability</h2>
<p>To the maximum extent permitted by law, Love Allah and its operators are not liable for indirect, consequential or special damages arising from your use of the app.</p>

<h2>Changes</h2>
<p>We may revise these terms occasionally. Material changes will be notified in the app.</p>

<h2>Contact</h2>
<p>Questions or complaints: <a href="mailto:hello@loveallah.app">hello@loveallah.app</a>.</p>
		<?php return ob_get_clean();
	}

	private static function send_manifest() : void {
		$base = home_url( '/' );
		$plugin_assets = LA_URL . 'assets/icons';
		$brand = get_option( 'la_brand_color', '#ED1C6C' );

		$manifest = [
			'name'              => 'Love Allah',
			'short_name'        => 'Love Allah',
			'description'       => 'Draw closer to Allah swt — prayer, dhikr, nasheeds and a feed curated for the heart.',
			'start_url'         => $base . '?source=pwa',
			'scope'             => $base,
			'display'           => 'standalone',
			'orientation'       => 'portrait',
			'background_color'  => '#FFFFFF',
			'theme_color'       => $brand,
			'lang'              => 'en',
			'dir'               => 'ltr',
			'categories'        => [ 'lifestyle', 'social', 'education' ],
			'icons'             => [
				[ 'src' => $plugin_assets . '/icon-192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
				[ 'src' => $plugin_assets . '/icon-384.png',          'sizes' => '384x384', 'type' => 'image/png', 'purpose' => 'any' ],
				[ 'src' => $plugin_assets . '/icon-512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
				[ 'src' => $plugin_assets . '/icon-192-maskable.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ],
				[ 'src' => $plugin_assets . '/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
			],
			'shortcuts'         => [
				[ 'name' => 'Dhikr',     'url' => '/dhikr',     'icons' => [ [ 'src' => $plugin_assets . '/icon-192.png', 'sizes' => '192x192' ] ] ],
				[ 'name' => 'Nasheeds',  'url' => '/nasheed',   'icons' => [ [ 'src' => $plugin_assets . '/icon-192.png', 'sizes' => '192x192' ] ] ],
				[ 'name' => 'Masjid',    'url' => '/masjid',    'icons' => [ [ 'src' => $plugin_assets . '/icon-192.png', 'sizes' => '192x192' ] ] ],
			],
			'related_applications'      => [],
			'prefer_related_applications' => false,
		];

		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private static function send_sw() : void {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: public, max-age=300' ); // SW itself updated often

		$version = LA_VERSION;
		?>
/* Love Allah service worker v<?php echo esc_js( $version ); ?> */
const CACHE_NAME = 'la-v<?php echo esc_js( $version ); ?>';
const STATIC_ASSETS = [
	'/',
	'/manifest.json',
];

self.addEventListener('install', (e) => {
	e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(STATIC_ASSETS)).catch(() => {}));
	self.skipWaiting();
});

self.addEventListener('activate', (e) => {
	e.waitUntil(
		caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
	);
	self.clients.claim();
});

self.addEventListener('fetch', (e) => {
	const { request } = e;
	const url = new URL(request.url);

	// Only handle same-origin GETs. Skip API + admin + uploads.
	if (request.method !== 'GET') return;
	if (url.origin !== self.location.origin) return;
	if (url.pathname.startsWith('/wp-admin')) return;
	if (url.pathname.startsWith('/wp-login')) return;
	if (url.pathname.startsWith('/wp-json'))  return;
	if (url.pathname.startsWith('/wp-content/uploads')) return;

	// Network-first for HTML (so users get fresh feeds); cache fallback offline.
	if (request.headers.get('accept')?.includes('text/html')) {
		e.respondWith(
			fetch(request).then(res => {
				const copy = res.clone();
				caches.open(CACHE_NAME).then(c => c.put(request, copy)).catch(() => {});
				return res;
			}).catch(() => caches.match(request).then(m => m || caches.match('/')))
		);
		return;
	}

	// Cache-first for static assets (CSS, JS, images)
	if (/\.(css|js|png|jpg|jpeg|svg|webp|ico|woff2?)$/i.test(url.pathname)) {
		e.respondWith(
			caches.match(request).then(cached => cached || fetch(request).then(res => {
				const copy = res.clone();
				caches.open(CACHE_NAME).then(c => c.put(request, copy)).catch(() => {});
				return res;
			}))
		);
	}
});

// Web push handler (used later when masjids send notifications)
self.addEventListener('push', (e) => {
	const data = (() => { try { return e.data?.json() || {}; } catch (_) { return {}; } })();
	const title = data.title || 'Love Allah';
	const opts = {
		body: data.body || '',
		icon: '/wp-content/plugins/loveallah/assets/icons/icon-192.png',
		badge: '/wp-content/plugins/loveallah/assets/icons/icon-192.png',
		data: { url: data.url || '/' },
	};
	e.waitUntil(self.registration.showNotification(title, opts));
});

self.addEventListener('notificationclick', (e) => {
	e.notification.close();
	const url = e.notification.data?.url || '/';
	e.waitUntil(clients.openWindow(url));
});
		<?php
		exit;
	}

	private static function send_assetlinks() : void {
		// JSON for Android Digital Asset Links. Lists every SHA-256 cert that
		// should be allowed to open this domain without a Chrome URL bar.
		//
		// We always include TWO fingerprints (Play Console / Wave 85):
		//  1. The Play APP-SIGNING key — what Google uses to sign installs
		//     distributed via Play Store. This is the production key.
		//  2. The UPLOAD key — our local Bubblewrap keystore. Same key the
		//     debug APK on a developer's device is signed with, plus the
		//     key used to sign uploads to Play Console.
		//
		// Without (1), Play-installed apps show the URL bar. Without (2),
		// sideloaded test builds show the URL bar. Listing both means every
		// install path works.
		$package = get_option( 'la_android_package', 'app.loveallah.app' );

		// Production app-signing SHA-256 from Play Console → App signing
		// (the one Google manages — we opted into Play App Signing). Hardcoded
		// because it's set in Play Console once and never changes.
		$play_signing_sha256 = '1E:0E:64:DD:9B:98:CB:7C:51:E4:12:88:C6:56:96:1B:33:6B:BF:B8:EE:75:A6:D2:72:3E:74:98:D4:1A:46:12';

		// Upload key SHA-256 from our Bubblewrap keystore. Falls back to the
		// la_android_sha256 wp_option for back-compat with the original wiring.
		$upload_sha256 = get_option( 'la_android_sha256', '' );

		$fingerprints = [ $play_signing_sha256 ];
		if ( $upload_sha256 && strtoupper( $upload_sha256 ) !== strtoupper( $play_signing_sha256 ) ) {
			$fingerprints[] = $upload_sha256;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );

		echo wp_json_encode( [ [
			'relation'  => [ 'delegate_permission/common.handle_all_urls' ],
			'target'    => [
				'namespace'                => 'android_app',
				'package_name'             => $package,
				'sha256_cert_fingerprints' => $fingerprints,
			],
		] ], JSON_PRETTY_PRINT );
		exit;
	}

	public static function head_tags() : void {
		$brand = get_option( 'la_brand_color', '#ED1C6C' );
		$icons = LA_URL . 'assets/icons/';
		$splash = LA_URL . 'assets/ios-splash/';
		?>
		<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.json' ) ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $brand ); ?>">
		<meta name="mobile-web-app-capable" content="yes">

		<!-- Google Search Console ownership verification for loveallah.app.
		     Required so the Play Console Org-tier conversion can verify
		     domain ownership (Play Console = adiljzhome account, this token
		     was issued to the same account so verification matches). -->
		<meta name="google-site-verification" content="GBlHuQcm03wqt5GEHPkrxKC51uhlQdvoUwKDyWzuzx8">

		<!-- ─── iOS / Safari PWA ───
		     'apple-mobile-web-app-capable' is deprecated but still respected on
		     iOS 16. 'mobile-web-app-capable' is the modern equivalent (above). -->
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-title" content="Love Allah">
		<meta name="format-detection" content="telephone=no">

		<!-- favicons — desktop browser tab + bookmarks. Wave 38: real
		     16/32px composited variants now exist instead of relying on a
		     downscaled 192px which blurred on retina displays. -->
		<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $icons . 'favicon-32.png' ); ?>">
		<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( $icons . 'favicon-16.png' ); ?>">

		<!-- apple-touch-icon — iPhone home-screen icon. 180×180 is the
		     canonical size; older devices fall back to other sizes. Wave 38:
		     dedicated 180px asset so iOS no longer downscales the 192px
		     and loses crispness on the homescreen. -->
		<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icons . 'apple-touch-icon-180.png' ); ?>">
		<link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url( $icons . 'icon-152.png' ); ?>">
		<link rel="apple-touch-icon" sizes="144x144" href="<?php echo esc_url( $icons . 'icon-144.png' ); ?>">
		<link rel="apple-touch-icon" sizes="120x120" href="<?php echo esc_url( $icons . 'icon-128.png' ); ?>">
		<link rel="apple-touch-icon" href="<?php echo esc_url( $icons . 'apple-touch-icon-180.png' ); ?>">
		<link rel="mask-icon" href="<?php echo esc_url( $icons . 'icon.svg' ); ?>" color="<?php echo esc_attr( $brand ); ?>">

		<!-- iOS splash screens. Each device size needs its own bitmap to avoid
		     a white flash on app launch. Generated from icon.svg at build time —
		     missing ones simply fall back to a white screen (acceptable). -->
		<?php $ios_splashes = [
			// [width, height, density, orientation]
			[ 1290, 2796, 3, 'portrait', 'iphone15promax' ],
			[ 1179, 2556, 3, 'portrait', 'iphone15pro' ],
			[ 1170, 2532, 3, 'portrait', 'iphone13pro' ],
			[ 1284, 2778, 3, 'portrait', 'iphone12promax' ],
			[ 1125, 2436, 3, 'portrait', 'iphonex' ],
			[ 828,  1792, 2, 'portrait', 'iphonexr' ],
			[ 1242, 2208, 3, 'portrait', 'iphone8plus' ],
			[ 750,  1334, 2, 'portrait', 'iphone8' ],
			[ 640,  1136, 2, 'portrait', 'iphonese' ],
		];
		foreach ( $ios_splashes as $s ) {
			$file = $splash . 'splash-' . $s[0] . 'x' . $s[1] . '.png';
			?>
			<link rel="apple-touch-startup-image"
				media="screen and (device-width: <?php echo (int) ( $s[0] / $s[2] ); ?>px) and (device-height: <?php echo (int) ( $s[1] / $s[2] ); ?>px) and (-webkit-device-pixel-ratio: <?php echo (int) $s[2]; ?>) and (orientation: portrait)"
				href="<?php echo esc_url( $file ); ?>">
			<?php
		}
		?>
		<?php
	}

	public static function register_sw_script() : void {
		?>
		<script>
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function() {
				navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function(){});
			});
		}
		</script>
		<?php
	}

	/** Run once on activation: flush rewrites so /manifest.json etc resolve */
	public static function on_activate() : void {
		self::rewrites();
		flush_rewrite_rules();
	}
}
