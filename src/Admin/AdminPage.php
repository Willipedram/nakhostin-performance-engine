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
		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'nakhostin-performance-engine' ),
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
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix && 0 !== strpos( $hook_suffix, self::SLUG . '_page_' ) ) {
			return;
		}

		wp_enqueue_style( 'npe-admin', NPE_URL . 'assets/admin/admin.css', array(), NPE_VERSION );
		if ( is_rtl() ) {
			wp_enqueue_style( 'npe-admin-rtl', NPE_URL . 'assets/admin/admin-rtl.css', array( 'npe-admin' ), NPE_VERSION );
		}
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage NPE settings.', 'nakhostin-performance-engine' ) );
		}

		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'Settings', 'nakhostin-performance-engine' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Configure NPE modules and conservative runtime defaults.',
					'nakhostin-performance-engine'
				);
				?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( 'npe_settings_group' ); ?>
				<?php $this->render_settings(); ?>
				<?php submit_button(); ?>
			</form>

		</div>
		<?php
	}

	private function render_settings(): void {
		$settings = $this->settings->all();
		?>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'General', 'nakhostin-performance-engine' ); ?></h2>
			<label>
				<input type="hidden" name="npe_settings[general][remove_data_on_uninstall]" value="0">
				<input type="checkbox" name="npe_settings[general][remove_data_on_uninstall]" value="1"
					<?php checked( $settings['general']['remove_data_on_uninstall'] ); ?>>
				<?php
				echo esc_html__(
					'Remove NPE settings when the plugin is uninstalled',
					'nakhostin-performance-engine'
				);
				?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: when WordPress deletes the plugin, NPE-owned settings, manifests, metrics, queues, and cache files are permanently removed.', 'nakhostin-performance-engine' ); ?></p>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Automatic DOM learning', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Gradually learns from eligible public pages that real visitors request. Analysis runs later through WP-Cron and never blocks the visitor response.', 'nakhostin-performance-engine' ); ?></p>
			<label>
				<input type="hidden" name="npe_settings[dom][enabled]" value="0">
				<input type="checkbox" name="npe_settings[dom][enabled]" value="1" <?php checked( $settings['dom']['enabled'] ); ?>>
				<?php echo esc_html__( 'Enable automatic DOM learning', 'nakhostin-performance-engine' ); ?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: only anonymous, query-free, same-site public URLs are queued. Account, cart, checkout, administration, logged-in, and analysis requests are excluded.', 'nakhostin-performance-engine' ); ?></p>
			<p><label><?php echo esc_html__( 'Daily sampling rate (percent)', 'nakhostin-performance-engine' ); ?><br>
				<input type="number" min="1" max="100" name="npe_settings[dom][sample_rate]" value="<?php echo esc_attr( $settings['dom']['sample_rate'] ); ?>">
			</label><span class="description"><?php echo esc_html__( 'A deterministic daily sample limits database writes and background traffic. Ten percent is recommended for normal production sites.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Rescan cooldown (hours)', 'nakhostin-performance-engine' ); ?><br>
				<input type="number" min="1" max="720" name="npe_settings[dom][cooldown_hours]" value="<?php echo esc_attr( $settings['dom']['cooldown_hours'] ); ?>">
			</label><span class="description"><?php echo esc_html__( 'The same normalized page is not queued again during this interval. Query strings are never stored.', 'nakhostin-performance-engine' ); ?></span></p>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'LiteSpeed Cache compatibility', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'NPE uses only public LiteSpeed hooks and never changes LiteSpeed Cache settings.', 'nakhostin-performance-engine' ); ?></p>
			<label>
				<input type="hidden" name="npe_settings[litespeed][enabled]" value="0">
				<input type="checkbox" name="npe_settings[litespeed][enabled]" value="1" <?php checked( $settings['litespeed']['enabled'] ); ?>>
				<?php echo esc_html__( 'Enable automatic compatibility safeguards when LiteSpeed Cache is active', 'nakhostin-performance-engine' ); ?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: prevents overlapping NPE and LiteSpeed optimization behavior. It does not change any LiteSpeed setting.', 'nakhostin-performance-engine' ); ?></p>
			<fieldset>
				<legend><?php echo esc_html__( 'Integration mode', 'nakhostin-performance-engine' ); ?></legend>
				<label><input type="radio" name="npe_settings[litespeed][mode]" value="independent" <?php checked( $settings['litespeed']['mode'], 'independent' ); ?>> <?php echo esc_html__( 'Independent — NPE owns enabled cache and optimization layers', 'nakhostin-performance-engine' ); ?></label><br>
				<label><input type="radio" name="npe_settings[litespeed][mode]" value="compatible" <?php checked( $settings['litespeed']['mode'], 'compatible' ); ?>> <?php echo esc_html__( 'Compatible — avoid competing cache and optimization layers', 'nakhostin-performance-engine' ); ?></label><br>
				<label><input type="radio" name="npe_settings[litespeed][mode]" value="cooperative" <?php checked( $settings['litespeed']['mode'], 'cooperative' ); ?>> <?php echo esc_html__( 'Cooperative — also share scoped tags, TTL, vary, and purge signals', 'nakhostin-performance-engine' ); ?></label>
			</fieldset>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Full Page Cache', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Application-level caching is disabled by default. Private WordPress and WooCommerce requests are always bypassed.', 'nakhostin-performance-engine' ); ?></p>
			<label>
				<input type="hidden" name="npe_settings[cache][enabled]" value="0">
				<input type="checkbox" name="npe_settings[cache][enabled]" value="1" <?php checked( $settings['cache']['enabled'] ); ?>>
				<?php echo esc_html__( 'Enable NPE full page cache', 'nakhostin-performance-engine' ); ?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: stores eligible public HTML responses. Logged-in, private, cart, checkout, account, session, and sensitive requests continue to bypass the cache.', 'nakhostin-performance-engine' ); ?></p>
			<p><label><?php echo esc_html__( 'TTL (seconds)', 'nakhostin-performance-engine' ); ?><br>
				<input type="number" min="30" max="86400" name="npe_settings[cache][ttl]" value="<?php echo esc_attr( $settings['cache']['ttl'] ); ?>">
			</label><span class="description"><?php echo esc_html__( 'How long a fresh public cache entry may be served.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Stale window (seconds)', 'nakhostin-performance-engine' ); ?><br>
				<input type="number" min="0" max="3600" name="npe_settings[cache][stale_ttl]" value="<?php echo esc_attr( $settings['cache']['stale_ttl'] ); ?>">
			</label><span class="description"><?php echo esc_html__( 'How long an expired entry may remain available for controlled stale handling.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Allowed query parameters (comma separated)', 'nakhostin-performance-engine' ); ?><br>
				<input type="text" class="regular-text" name="npe_settings[cache][allowed_query_parameters]" value="<?php echo esc_attr( implode( ', ', $settings['cache']['allowed_query_parameters'] ) ); ?>">
			</label><span class="description"><?php echo esc_html__( 'Only these non-sensitive query parameters may create separate public cache variants. Leave empty unless required.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Excluded paths (one per line)', 'nakhostin-performance-engine' ); ?><br>
				<textarea class="large-text" rows="4" name="npe_settings[cache][excluded_paths]"><?php echo esc_html( implode( "\n", $settings['cache']['excluded_paths'] ) ); ?></textarea>
			</label><span class="description"><?php echo esc_html__( 'Matching URL paths always bypass the NPE page cache.', 'nakhostin-performance-engine' ); ?></span></p>
			<label>
				<input type="hidden" name="npe_settings[cache][warm_homepage]" value="0">
				<input type="checkbox" name="npe_settings[cache][warm_homepage]" value="1" <?php checked( $settings['cache']['warm_homepage'] ); ?>>
				<?php echo esc_html__( 'Warm the homepage after full cache invalidation', 'nakhostin-performance-engine' ); ?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: queues a non-blocking homepage request after a full invalidation so the first visitor is less likely to encounter a cold cache.', 'nakhostin-performance-engine' ); ?></p>
			<p><label><?php echo esc_html__( 'Important product IDs (comma separated)', 'nakhostin-performance-engine' ); ?><br>
				<input type="text" class="regular-text" name="npe_settings[cache][important_product_ids]" value="<?php echo esc_attr( implode( ', ', $settings['cache']['important_product_ids'] ) ); ?>">
			</label><span class="description"><?php echo esc_html__( 'These products are prioritized for asynchronous cache warmup; publishing is not blocked.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Important product category IDs (comma separated)', 'nakhostin-performance-engine' ); ?><br>
				<input type="text" class="regular-text" name="npe_settings[cache][important_category_ids]" value="<?php echo esc_attr( implode( ', ', $settings['cache']['important_category_ids'] ) ); ?>">
			</label><span class="description"><?php echo esc_html__( 'These product categories are prioritized for asynchronous cache warmup.', 'nakhostin-performance-engine' ); ?></span></p>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Performance monitoring', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Collect a bounded, privacy-safe sample of backend PHP measurements. These values are not browser TTFB.', 'nakhostin-performance-engine' ); ?></p>
			<label><input type="hidden" name="npe_settings[performance][enabled]" value="0"><input type="checkbox" name="npe_settings[performance][enabled]" value="1" <?php checked( $settings['performance']['enabled'] ); ?>> <?php echo esc_html__( 'Enable diagnostic sampling', 'nakhostin-performance-engine' ); ?></label>
			<p class="description"><?php echo esc_html__( 'Effect: records bounded backend timing and memory samples without storing private URLs, cookies, or request bodies.', 'nakhostin-performance-engine' ); ?></p>
			<p><label><?php echo esc_html__( 'Sample rate (percent)', 'nakhostin-performance-engine' ); ?><br><input type="number" min="1" max="100" name="npe_settings[performance][sample_rate]" value="<?php echo esc_attr( $settings['performance']['sample_rate'] ); ?>"></label><span class="description"><?php echo esc_html__( 'Lower values reduce monitoring overhead; 10 percent is the conservative default.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Retention (days)', 'nakhostin-performance-engine' ); ?><br><input type="number" min="1" max="90" name="npe_settings[performance][retention_days]" value="<?php echo esc_attr( $settings['performance']['retention_days'] ); ?>"></label><span class="description"><?php echo esc_html__( 'Older performance samples are removed after this period.', 'nakhostin-performance-engine' ); ?></span></p>
			<p><label><?php echo esc_html__( 'Maximum samples', 'nakhostin-performance-engine' ); ?><br><input type="number" min="10" max="2000" name="npe_settings[performance][max_samples]" value="<?php echo esc_attr( $settings['performance']['max_samples'] ); ?>"></label><span class="description"><?php echo esc_html__( 'Caps stored history so monitoring data cannot grow without limit.', 'nakhostin-performance-engine' ); ?></span></p>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Debugging', 'nakhostin-performance-engine' ); ?></h2>
			<label>
				<input type="hidden" name="npe_settings[debugging][enabled]" value="0">
				<input type="checkbox" name="npe_settings[debugging][enabled]" value="1"
					<?php checked( $settings['debugging']['enabled'] ); ?>>
				<?php
				echo esc_html__(
					'Enable redacted debug logging when WordPress debugging is active',
					'nakhostin-performance-engine'
				);
				?>
			</label>
			<p class="description"><?php echo esc_html__( 'Effect: writes redacted diagnostic messages only while WordPress debugging is also enabled; secrets and personal data are filtered.', 'nakhostin-performance-engine' ); ?></p>
		</div>
		<div class="npe-card">
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
		</div>
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
