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
		}
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
		// JSON for Android Digital Asset Links — populated once you have the
		// signing key fingerprint from bubblewrap. Stored as an option so it
		// can be updated without code edits.
		$sha256 = get_option( 'la_android_sha256', '' );
		$package = get_option( 'la_android_package', 'app.loveallah.app' );

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );

		if ( empty( $sha256 ) ) {
			echo wp_json_encode( [ [
				'_note' => 'Android signing key SHA-256 not yet configured. Set in wp-admin → Love Allah → Settings, or via wp option update la_android_sha256 "AA:BB:..."',
			] ], JSON_PRETTY_PRINT );
			exit;
		}

		echo wp_json_encode( [ [
			'relation'  => [ 'delegate_permission/common.handle_all_urls' ],
			'target'    => [
				'namespace'                => 'android_app',
				'package_name'             => $package,
				'sha256_cert_fingerprints' => [ $sha256 ],
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

		<!-- ─── iOS / Safari PWA ───
		     'apple-mobile-web-app-capable' is deprecated but still respected on
		     iOS 16. 'mobile-web-app-capable' is the modern equivalent (above). -->
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-title" content="Love Allah">
		<meta name="format-detection" content="telephone=no">

		<!-- apple-touch-icon — iPhone home-screen icon. 180×180 is the
		     reference size; older devices fall back to others if 180 missing. -->
		<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icons . 'icon-192.png' ); ?>">
		<link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url( $icons . 'icon-152.png' ); ?>">
		<link rel="apple-touch-icon" sizes="144x144" href="<?php echo esc_url( $icons . 'icon-144.png' ); ?>">
		<link rel="apple-touch-icon" sizes="120x120" href="<?php echo esc_url( $icons . 'icon-128.png' ); ?>">
		<link rel="apple-touch-icon" href="<?php echo esc_url( $icons . 'icon-192.png' ); ?>">
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
