<?php
/**
 * NPE administration screen.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class AdminPage {
	public const SLUG = 'nakhostin-performance-engine';
	public const SETTINGS_SLUG = 'npe-settings';

	/** @var Settings */
	private $settings;

	/** @var Diagnostics */
	private $diagnostics;

	/** @var Capabilities */
	private $capabilities;

	/** @var FeatureFlags */
	private $features;

	public function __construct(
		Settings $settings,
		Diagnostics $diagnostics,
		Capabilities $capabilities,
		FeatureFlags $features
	) {
		$this->settings     = $settings;
		$this->diagnostics  = $diagnostics;
		$this->capabilities = $capabilities;
		$this->features     = $features;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu(): void {
		AdminShell::submenu(
			self::SLUG,
			__( 'NPE Settings', 'nakhostin-performance-engine' ),
			__( 'Settings', 'nakhostin-performance-engine' ),
			Capabilities::MANAGE,
			self::SETTINGS_SLUG,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'npe_settings_group',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( AdminShell::is_page( $hook_suffix, self::SETTINGS_SLUG ) ) {
			wp_enqueue_script( 'npe-settings', NPE_URL . 'assets/admin/settings.js', array(), (string) filemtime( NPE_PATH . 'assets/admin/settings.js' ), true );
		}
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage NPE settings.', 'nakhostin-performance-engine' ) );
		}

		$tabs = $this->settings_tabs();
		$active_tab = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state.
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap npe-admin npe-settings-page" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<p class="npe-page-intro"><?php echo esc_html__( 'Configure performance features with safe defaults. Every option explains its effect before you enable it.', 'nakhostin-performance-engine' ); ?></p>
			<?php if ( function_exists( 'settings_errors' ) ) { settings_errors(); } ?>
			<nav class="nav-tab-wrapper npe-settings-tabs" aria-label="<?php echo esc_attr__( 'Settings sections', 'nakhostin-performance-engine' ); ?>">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<a id="npe-settings-tab-<?php echo esc_attr( $key ); ?>" class="nav-tab<?php echo $active_tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::SETTINGS_SLUG, 'section' => $key ), admin_url( 'admin.php' ) ) . '#npe-settings-' . $key ); ?>" data-npe-tab="<?php echo esc_attr( $key ); ?>" <?php echo $active_tab === $key ? 'aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form action="options.php" method="post" class="npe-settings-form">
				<?php settings_fields( 'npe_settings_group' ); ?>
				<div class="npe-settings-layout"><div class="npe-settings-main"><?php $this->render_settings( $active_tab ); ?></div><?php $this->render_settings_sidebar(); ?></div>
				<div class="npe-settings-actions">
					<span role="status" data-npe-save-status data-dirty-label="<?php echo esc_attr__( 'You have unsaved changes.', 'nakhostin-performance-engine' ); ?>"><?php echo esc_html__( 'Changes take effect after saving.', 'nakhostin-performance-engine' ); ?></span>
					<?php submit_button( __( 'Save settings', 'nakhostin-performance-engine' ), 'primary', 'submit', false ); ?>
				</div>
			</form>

		</div>
		<?php
	}

	private function render_settings( string $active_tab = 'general' ): void {
		$settings = $this->settings->all();
		?>
		<section id="npe-settings-general" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-general" data-npe-section="general" <?php echo 'general' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'General', 'nakhostin-performance-engine' ); ?></h2>
			<details class="npe-advanced"><summary><?php echo esc_html__( 'Advanced settings', 'nakhostin-performance-engine' ); ?></summary>
			<?php AdminComponents::field( 'toggle', 'general', 'remove_data_on_uninstall', $settings['general']['remove_data_on_uninstall'], __( 'Remove NPE settings when the plugin is uninstalled', 'nakhostin-performance-engine' ), __( 'Effect: when WordPress deletes the plugin, NPE-owned settings, manifests, metrics, queues, and cache files are permanently removed.', 'nakhostin-performance-engine' ) ); ?>
			</details>
		</section>
		<section id="npe-settings-fonts" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-intelligence" data-npe-section="intelligence" <?php echo 'intelligence' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Page-aware font loading', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Detects font families, formats, weights, source URLs, and the pages that actually reference them during automatic page analysis.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'fonts', 'enabled', $settings['fonts']['enabled'], __( 'Replace analyzed font-only stylesheets with only the required font faces', 'nakhostin-performance-engine' ), __( 'Effect: on a previously analyzed public page, NPE can dequeue a stylesheet containing only @font-face rules, recreate only required variants, and enforce font-display: swap when the source uses auto, block, or no strategy. Mixed stylesheets, unknown pages, logged-in users, uncertain fonts, and LiteSpeed-compatible CSS optimization remain unchanged.', 'nakhostin-performance-engine' ) ); ?>
		</section>
		<section id="npe-settings-optimization" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-intelligence" data-npe-section="intelligence" <?php echo 'intelligence' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Frontend optimization runtime', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Runtime changes use only fresh precomputed page manifests. Missing, stale, uncertain, cart, checkout, account, and logged-in contexts keep every original asset.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'optimization', 'enabled', $settings['optimization']['enabled'], __( 'Enable manifest-driven frontend optimization', 'nakhostin-performance-engine' ), __( 'Applies approved optimizations when a public page has fresh analysis. Pages without reliable analysis keep their original assets.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'optimization', 'learning', $settings['optimization']['learning'], __( 'Learn page assets', 'nakhostin-performance-engine' ), __( 'Require three compatible anonymous observations before publishing CSS and JavaScript bundles.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'css', 'combine_enabled', $settings['css']['combine_enabled'], __( 'Combine compatible stylesheets', 'nakhostin-performance-engine' ), __( 'Merge a verified group of adjacent stylesheets for an analyzed page. Safe Mode can remain on.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'media', 'lazy_load', $settings['media']['lazy_load'], __( 'Native media lazy loading', 'nakhostin-performance-engine' ), __( 'Use WordPress loading rules for supported images and iframes. Explicit eager loading and high priority are preserved.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'optimization', 'safe_mode', $settings['optimization']['safe_mode'], __( 'Safe mode — keep unknown assets and disable automatic unloading', 'nakhostin-performance-engine' ), __( 'Keeps unknown files and prevents automatic unloading. Leave this enabled while checking compatibility.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'css', 'critical_css_enabled', $settings['css']['critical_css_enabled'], __( 'Inline stored critical CSS', 'nakhostin-performance-engine' ), __( 'With frontend optimization active and safe mode off, inserts stored first-screen CSS into the HTML for eligible pages.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'css', 'unused_css_enabled', $settings['css']['unused_css_enabled'], __( 'Allow explicitly approved unused CSS decisions', 'nakhostin-performance-engine' ), __( 'With safe mode off, allows NPE to skip stylesheets approved for removal by fresh page analysis. Check interactive elements after enabling.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'javascript', 'defer_enabled', $settings['javascript']['defer_enabled'], __( 'Apply dependency-safe JavaScript defer decisions', 'nakhostin-performance-engine' ), __( 'Runs approved scripts after HTML parsing while preserving dependency order. This can make the page render sooner.', 'nakhostin-performance-engine' ) ); ?>
			<details class="npe-advanced"><summary><?php echo esc_html__( 'Advanced settings', 'nakhostin-performance-engine' ); ?></summary>
			<?php AdminComponents::field( 'toggle', 'javascript', 'delay_enabled', $settings['javascript']['delay_enabled'], __( 'Experimental allowlist-only JavaScript delay', 'nakhostin-performance-engine' ), __( 'Records this experimental preference for diagnostics. The current version does not delay scripts when this is enabled.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'assets', 'unload_enabled', $settings['assets']['unload_enabled'], __( 'Unload only high-confidence approved assets', 'nakhostin-performance-engine' ), __( 'Skips approved files that analysis found unnecessary on this page. Safe mode prevents automatic unloading.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'assets', 'bundle_enabled', $settings['assets']['bundle_enabled'], __( 'Build and serve page-specific CSS and JavaScript bundles', 'nakhostin-performance-engine' ), __( 'Bundles are generated only by background analysis. Safe mode, unknown sources, inline data, modules, third-party files, protected commerce pages, and jQuery remain on their original handles.', 'nakhostin-performance-engine' ) ); ?>
			</details>
			<?php AdminComponents::field( 'toggle', 'fonts', 'preload_enabled', $settings['fonts']['preload_enabled'], __( 'Preload up to two critical WOFF2-preferred fonts', 'nakhostin-performance-engine' ), __( 'Asks the browser to fetch up to two important fonts earlier, which can reduce the time until the final text style appears.', 'nakhostin-performance-engine' ) ); ?>
		</section>
		<section id="npe-settings-dom" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-intelligence" data-npe-section="intelligence" <?php echo 'intelligence' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Automatic DOM learning', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Gradually learns from eligible public pages that real visitors request. Analysis runs later through WP-Cron and never blocks the visitor response.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'dom', 'enabled', $settings['dom']['enabled'], __( 'Enable automatic DOM learning', 'nakhostin-performance-engine' ), __( 'Effect: only anonymous, query-free, same-site public URLs are queued. Account, cart, checkout, administration, logged-in, and analysis requests are excluded.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'number', 'dom', 'sample_rate', $settings['dom']['sample_rate'], __( 'Visitor sampling rate (percent)', 'nakhostin-performance-engine' ), __( 'Eligible visits are sampled over time; cooldown and URL deduplication limit database writes and background traffic. Ten percent is recommended for normal production sites.', 'nakhostin-performance-engine' ), array( 1, 100 ) ); ?>
			<?php AdminComponents::field( 'number', 'dom', 'cooldown_hours', $settings['dom']['cooldown_hours'], __( 'Rescan cooldown (hours)', 'nakhostin-performance-engine' ), __( 'The same normalized page is not queued again during this interval. Query strings are never stored.', 'nakhostin-performance-engine' ), array( 1, 720 ) ); ?>
			<?php AdminComponents::field( 'number', 'dom', 'batch_size', $settings['dom']['batch_size'], __( 'Pages per scan batch', 'nakhostin-performance-engine' ), __( 'Processes several pages quickly while enforcing a short execution budget. Three pages is the recommended balance.', 'nakhostin-performance-engine' ), array( 1, 5 ) ); ?>
			<?php AdminComponents::field( 'number', 'dom', 'scan_interval', $settings['dom']['scan_interval'], __( 'Delay between scan batches (seconds)', 'nakhostin-performance-engine' ), __( 'Schedules the next WP-Cron batch after this delay. Actual execution depends on WordPress cron traffic.', 'nakhostin-performance-engine' ), array( 5, 300 ) ); ?>
		</section>
		<section id="npe-settings-litespeed" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-integrations" data-npe-section="integrations" <?php echo 'integrations' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'LiteSpeed Cache compatibility', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'NPE uses only public LiteSpeed hooks and never changes LiteSpeed Cache settings.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'litespeed', 'enabled', $settings['litespeed']['enabled'], __( 'Enable automatic compatibility safeguards when LiteSpeed Cache is active', 'nakhostin-performance-engine' ), __( 'Effect: prevents overlapping NPE and LiteSpeed optimization behavior. It does not change any LiteSpeed setting.', 'nakhostin-performance-engine' ) ); ?>
			<fieldset class="npe-mode-options"><legend><?php echo esc_html__( 'Integration mode', 'nakhostin-performance-engine' ); ?></legend>
			<?php AdminComponents::field( 'radio', 'litespeed', 'mode', $settings['litespeed']['mode'], __( 'Independent — NPE owns enabled cache and optimization layers', 'nakhostin-performance-engine' ), __( 'Lets NPE run its selected features independently. Coordinate overlapping features in the LiteSpeed dashboard.', 'nakhostin-performance-engine' ), array( 'independent' ) ); ?>
			<?php AdminComponents::field( 'radio', 'litespeed', 'mode', $settings['litespeed']['mode'], __( 'Compatible — avoid competing cache and optimization layers', 'nakhostin-performance-engine' ), __( 'Uses compatibility safeguards for features the plugins share. This is the default for mixed installations.', 'nakhostin-performance-engine' ), array( 'compatible' ) ); ?>
			<?php AdminComponents::field( 'radio', 'litespeed', 'mode', $settings['litespeed']['mode'], __( 'Cooperative — also share scoped tags, TTL, vary, and purge signals', 'nakhostin-performance-engine' ), __( 'Extends compatibility by sharing supported cache tags, lifetimes, and purge events with LiteSpeed.', 'nakhostin-performance-engine' ), array( 'cooperative' ) ); ?>
			</fieldset>
		</section>
		<section id="npe-settings-cache" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-cache" data-npe-section="cache" <?php echo 'cache' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Full Page Cache', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Application-level caching is disabled by default. Private WordPress and WooCommerce requests are always bypassed.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'cache', 'enabled', $settings['cache']['enabled'], __( 'Enable NPE full page cache', 'nakhostin-performance-engine' ), __( 'Effect: stores eligible public HTML responses. Logged-in, private, cart, checkout, account, session, and sensitive requests continue to bypass the cache.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'cache', 'early_cache', $settings['cache']['early_cache'], __( 'Enable the server-independent early page cache', 'nakhostin-performance-engine' ), __( 'Requires full page cache. NPE enables WP_CACHE where supported and installs its drop-in so public cache hits can skip normal WordPress startup. If setup is blocked, application caching remains available.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'number', 'cache', 'ttl', $settings['cache']['ttl'], __( 'TTL (seconds)', 'nakhostin-performance-engine' ), __( 'How long a fresh public cache entry may be served.', 'nakhostin-performance-engine' ), array( 30, 86400 ) ); ?>
			<?php AdminComponents::field( 'number', 'cache', 'stale_ttl', $settings['cache']['stale_ttl'], __( 'Stale window (seconds)', 'nakhostin-performance-engine' ), __( 'How long an expired entry may remain available for controlled stale handling.', 'nakhostin-performance-engine' ), array( 0, 3600 ) ); ?>
			<?php AdminComponents::field( 'text', 'cache', 'allowed_query_parameters', implode( ", ", $settings['cache']['allowed_query_parameters'] ), __( 'Allowed query parameters (comma separated)', 'nakhostin-performance-engine' ), __( 'Only these non-sensitive query parameters may create separate public cache variants. Leave empty unless required.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'textarea', 'cache', 'excluded_paths', implode( "\n", $settings['cache']['excluded_paths'] ), __( 'Excluded paths (one per line)', 'nakhostin-performance-engine' ), __( 'Matching URL paths always bypass the NPE page cache.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'toggle', 'cache', 'warm_homepage', $settings['cache']['warm_homepage'], __( 'Warm the homepage after full cache invalidation', 'nakhostin-performance-engine' ), __( 'Effect: queues a non-blocking homepage request after a full invalidation so the first visitor is less likely to encounter a cold cache.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'text', 'cache', 'important_product_ids', implode( ", ", $settings['cache']['important_product_ids'] ), __( 'Important product IDs (comma separated)', 'nakhostin-performance-engine' ), __( 'These products are prioritized for asynchronous cache warmup; publishing is not blocked.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'text', 'cache', 'important_category_ids', implode( ", ", $settings['cache']['important_category_ids'] ), __( 'Important product category IDs (comma separated)', 'nakhostin-performance-engine' ), __( 'These product categories are prioritized for asynchronous cache warmup.', 'nakhostin-performance-engine' ) ); ?>
		</section>
		<section id="npe-settings-performance" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-monitoring" data-npe-section="monitoring" <?php echo 'monitoring' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Performance monitoring', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Collect a bounded, privacy-safe sample of backend PHP measurements. These values are not browser TTFB.', 'nakhostin-performance-engine' ); ?></p>
			<?php AdminComponents::field( 'toggle', 'performance', 'enabled', $settings['performance']['enabled'], __( 'Enable diagnostic sampling', 'nakhostin-performance-engine' ), __( 'Effect: records bounded backend timing and memory samples without storing private URLs, cookies, or request bodies.', 'nakhostin-performance-engine' ) ); ?>
			<?php AdminComponents::field( 'number', 'performance', 'sample_rate', $settings['performance']['sample_rate'], __( 'Sample rate (percent)', 'nakhostin-performance-engine' ), __( 'Lower values reduce monitoring overhead; 10 percent is the conservative default.', 'nakhostin-performance-engine' ), array( 1, 100 ) ); ?>
			<?php AdminComponents::field( 'number', 'performance', 'retention_days', $settings['performance']['retention_days'], __( 'Retention (days)', 'nakhostin-performance-engine' ), __( 'Older performance samples are removed after this period.', 'nakhostin-performance-engine' ), array( 1, 90 ) ); ?>
			<?php AdminComponents::field( 'number', 'performance', 'max_samples', $settings['performance']['max_samples'], __( 'Maximum samples', 'nakhostin-performance-engine' ), __( 'Caps stored history so monitoring data cannot grow without limit.', 'nakhostin-performance-engine' ), array( 10, 2000 ) ); ?>
		</section>
		<section id="npe-settings-debug" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-general" data-npe-section="general" <?php echo 'general' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Debugging', 'nakhostin-performance-engine' ); ?></h2>
			<?php AdminComponents::field( 'toggle', 'debugging', 'enabled', $settings['debugging']['enabled'], __( 'Enable redacted debug logging when WordPress debugging is active', 'nakhostin-performance-engine' ), __( 'Effect: writes redacted diagnostic messages only while WordPress debugging is also enabled; secrets and personal data are filtered.', 'nakhostin-performance-engine' ) ); ?>
		</section>
		<section id="npe-settings-modules" class="npe-card npe-settings-panel" aria-labelledby="npe-settings-tab-general" data-npe-section="general" <?php echo 'general' !== $active_tab ? 'hidden' : ''; ?>>
			<h2><?php echo esc_html__( 'Module availability', 'nakhostin-performance-engine' ); ?></h2>
			<p>
				<?php
				echo esc_html__(
					'Availability reflects the modules implemented in this release. Unavailable modules remain reserved for future phases and cannot be enabled.',
					'nakhostin-performance-engine'
				);
				?>
			</p>
			<ul class="npe-module-list">
				<?php foreach ( $this->module_labels() as $key => $label ) : ?>
					<li>
						<strong><?php echo esc_html( $label ); ?></strong>
						— <?php echo esc_html( $this->feature_status( $key ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}


	private function settings_tabs(): array {
		return array(
			'general'      => array( 'label' => __( 'General', 'nakhostin-performance-engine' ), 'icon' => 'dashicons-admin-generic' ),
			'intelligence' => array( 'label' => __( 'Intelligence', 'nakhostin-performance-engine' ), 'icon' => 'dashicons-lightbulb' ),
			'cache'        => array( 'label' => __( 'Cache', 'nakhostin-performance-engine' ), 'icon' => 'dashicons-database' ),
			'integrations' => array( 'label' => __( 'Integrations', 'nakhostin-performance-engine' ), 'icon' => 'dashicons-admin-plugins' ),
			'monitoring'   => array( 'label' => __( 'Monitoring', 'nakhostin-performance-engine' ), 'icon' => 'dashicons-chart-area' ),
		);
	}

	private function render_settings_sidebar(): void {
		$settings = $this->settings->all();
		$active = array(
			__( 'Automatic DOM learning', 'nakhostin-performance-engine' ) => ! empty( $settings['dom']['enabled'] ),
			__( 'Page-aware font loading', 'nakhostin-performance-engine' ) => ! empty( $settings['fonts']['enabled'] ),
			__( 'Full Page Cache', 'nakhostin-performance-engine' ) => ! empty( $settings['cache']['enabled'] ),
			__( 'Performance monitoring', 'nakhostin-performance-engine' ) => ! empty( $settings['performance']['enabled'] ),
			__( 'LiteSpeed safeguards', 'nakhostin-performance-engine' ) => ! empty( $settings['litespeed']['enabled'] ),
		);
		?>
		<aside class="npe-settings-sidebar" aria-label="<?php echo esc_attr__( 'Configuration summary', 'nakhostin-performance-engine' ); ?>">
			<div class="npe-card npe-settings-summary"><h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html__( 'Configuration summary', 'nakhostin-performance-engine' ); ?></h2><ul>
			<?php foreach ( $active as $label => $enabled ) : ?><li><span><?php echo esc_html( $label ); ?></span><span class="npe-status-badge <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>"><span class="dashicons <?php echo $enabled ? 'dashicons-yes' : 'dashicons-minus'; ?>" aria-hidden="true"></span><?php echo $enabled ? esc_html__( 'Enabled', 'nakhostin-performance-engine' ) : esc_html__( 'Disabled', 'nakhostin-performance-engine' ); ?></span></li><?php endforeach; ?>
			</ul></div>
			<div class="npe-card npe-settings-help"><h2><span class="dashicons dashicons-shield" aria-hidden="true"></span><?php echo esc_html__( 'Safe configuration', 'nakhostin-performance-engine' ); ?></h2><p><?php echo esc_html__( 'Enable one runtime feature at a time, save, purge relevant caches, and verify important storefront pages before enabling the next feature.', 'nakhostin-performance-engine' ); ?></p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . DiagnosticsAdminPage::SLUG ) ); ?>"><?php echo esc_html__( 'Open diagnostics', 'nakhostin-performance-engine' ); ?></a></div>
		</aside>
		<?php
	}

	private function render_diagnostics( array $diagnostics ): void {
		?>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Diagnostics', 'nakhostin-performance-engine' ); ?></h2>
			<table class="widefat striped"><tbody>
			<?php foreach ( $diagnostics as $key => $value ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $this->diagnostic_labels()[ $key ] ?? $key ); ?></th>
					<td><?php echo esc_html( $this->format_diagnostic( $value ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function module_labels(): array {
		return array(
			'dom'         => __( 'DOM Intelligence', 'nakhostin-performance-engine' ),
			'css'         => __( 'CSS', 'nakhostin-performance-engine' ),
			'javascript'  => __( 'JavaScript', 'nakhostin-performance-engine' ),
			'cache'       => __( 'Cache', 'nakhostin-performance-engine' ),
			'woocommerce' => __( 'WooCommerce', 'nakhostin-performance-engine' ),
			'elementor'   => __( 'Elementor', 'nakhostin-performance-engine' ),
			'woodmart'    => __( 'WoodMart', 'nakhostin-performance-engine' ),
			'litespeed'   => __( 'LiteSpeed', 'nakhostin-performance-engine' ),
			'performance' => __( 'Performance', 'nakhostin-performance-engine' ),
		);
	}

	private function diagnostic_labels(): array {
		return array(
			'wordpress_version' => __( 'WordPress version', 'nakhostin-performance-engine' ),
			'php_version'       => __( 'PHP version', 'nakhostin-performance-engine' ),
			'server_software'   => __( 'Server software', 'nakhostin-performance-engine' ),
			'https'             => __( 'HTTPS', 'nakhostin-performance-engine' ),
			'multisite'         => __( 'Multisite', 'nakhostin-performance-engine' ),
			'woocommerce'       => __( 'WooCommerce detected', 'nakhostin-performance-engine' ),
			'elementor'         => __( 'Elementor detected', 'nakhostin-performance-engine' ),
			'litespeed_cache'   => __( 'LiteSpeed Cache detected', 'nakhostin-performance-engine' ),
			'object_cache'      => __( 'External object cache', 'nakhostin-performance-engine' ),
			'redis'             => __( 'Redis detected', 'nakhostin-performance-engine' ),
		);
	}

	private function feature_status( string $feature ): string {
		return $this->features->is_available( $feature )
			? __( 'Available', 'nakhostin-performance-engine' )
			: __( 'Not available', 'nakhostin-performance-engine' );
	}

	private function format_diagnostic( $value ): string {
		if ( ! is_bool( $value ) ) {
			return (string) $value;
		}

		return $value
			? __( 'Yes', 'nakhostin-performance-engine' )
			: __( 'No', 'nakhostin-performance-engine' );
	}
}
