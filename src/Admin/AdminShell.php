<?php
/**
 * Shared registration, navigation, and layout for NPE administration.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

final class AdminShell {
	private static $pages = array();
	private static $hooks = array();

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function menu( string $title, string $label, string $capability, string $slug, callable $render, string $icon, int $position ): void {
		self::$pages[ $slug ] = array( 'title' => $title, 'label' => __( 'Overview', 'nakhostin-performance-engine' ), 'capability' => $capability );
		self::$hooks[ add_menu_page( $title, $label, $capability, $slug, self::callback( $slug, $render ), $icon, $position ) ] = $slug;
	}

	public static function submenu( string $parent, string $title, string $label, string $capability, string $slug, callable $render ): void {
		// WordPress uses the root callback for the overview submenu's identical URL.
		$callback = null;
		if ( $parent !== $slug ) {
			self::$pages[ $slug ] = array( 'title' => $title, 'label' => $label, 'capability' => $capability );
			$callback = self::callback( $slug, $render );
		}
		if ( null === $callback ) {
			add_submenu_page( $parent, $title, $label, $capability, $slug );
			return;
		}
		$hook = add_submenu_page( $parent, $title, $label, $capability, $slug, $callback );
		if ( $hook ) {
			self::$hooks[ $hook ] = $slug;
		}
	}

	public static function is_page( string $hook, string $slug ): bool {
		return ( self::$hooks[ $hook ] ?? null ) === $slug;
	}

	public static function enqueue( string $hook ): void {
		if ( empty( self::$hooks[ $hook ] ) ) {
			return;
		}
		wp_enqueue_style( 'npe-admin', NPE_URL . 'assets/admin/admin.css', array( 'dashicons' ), (string) filemtime( NPE_PATH . 'assets/admin/admin.css' ) );
		if ( is_rtl() ) {
			wp_enqueue_style( 'npe-admin-rtl', NPE_URL . 'assets/admin/admin-rtl.css', array( 'npe-admin' ), (string) filemtime( NPE_PATH . 'assets/admin/admin-rtl.css' ) );
		}
		wp_enqueue_style( 'npe-admin-system', NPE_URL . 'assets/admin/design-system.css', array( is_rtl() ? 'npe-admin-rtl' : 'npe-admin' ), (string) filemtime( NPE_PATH . 'assets/admin/design-system.css' ) );
		wp_enqueue_script( 'npe-admin-shell', NPE_URL . 'assets/admin/shell.js', array(), (string) filemtime( NPE_PATH . 'assets/admin/shell.js' ), true );
	}

	private static function callback( string $slug, callable $render ): callable {
		return static function () use ( $slug, $render ): void {
			if ( ! current_user_can( self::$pages[ $slug ]['capability'] ) ) {
				wp_die( esc_html__( 'You are not allowed to view this NPE section.', 'nakhostin-performance-engine' ) );
			}
			self::render( $slug, $render );
		};
	}

	private static function render( string $active, callable $content ): void {
		$title = self::$pages[ $active ]['title'];
		?>
		<div class="wrap npe-admin npe-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>" data-table-label="<?php echo esc_attr__( 'Scrollable table', 'nakhostin-performance-engine' ); ?>">
			<a class="screen-reader-text npe-shell-skip" href="#npe-main"><?php echo esc_html__( 'Skip to NPE content', 'nakhostin-performance-engine' ); ?></a>
			<header class="npe-shell-header">
				<div>
					<nav class="npe-breadcrumb" aria-label="<?php echo esc_attr__( 'Breadcrumb', 'nakhostin-performance-engine' ); ?>">
						<?php if ( AdminPage::SLUG !== $active ) : ?>
							<a href="<?php echo esc_url( self::url( AdminPage::SLUG ) ); ?>">NPE</a><span aria-hidden="true">/</span>
							<span aria-current="page"><?php echo esc_html( self::$pages[ $active ]['label'] ); ?></span>
						<?php else : ?>
							<span>NPE</span>
						<?php endif; ?>
					</nav>
					<h1><?php echo esc_html( $title ); ?></h1>
				</div>
				<span class="npe-shell-symbol dashicons <?php echo esc_attr( self::icon( $active ) ); ?>" aria-hidden="true"></span>
			</header>
			<div class="npe-shell-grid">
				<details class="npe-shell-sidebar" open>
					<summary><?php echo esc_html__( 'NPE navigation', 'nakhostin-performance-engine' ); ?></summary>
					<nav aria-label="<?php echo esc_attr__( 'NPE navigation', 'nakhostin-performance-engine' ); ?>">
						<?php self::navigation( $active ); ?>
					</nav>
				</details>
				<main id="npe-main" class="npe-shell-content" tabindex="-1">
					<hr class="wp-header-end">
					<?php $content(); ?>
				</main>
			</div>
			<footer class="npe-shell-footer">
				<span class="npe-technical" dir="ltr"><?php echo esc_html( 'NPE ' . NPE_VERSION ); ?></span>
				<a href="<?php echo esc_url( self::url( DiagnosticsAdminPage::SLUG ) ); ?>"><?php echo esc_html__( 'Diagnostics', 'nakhostin-performance-engine' ); ?></a>
				<a href="https://github.com/Willipedram/nakhostin-performance-engine/tree/main/docs"><?php echo esc_html__( 'Documentation', 'nakhostin-performance-engine' ); ?></a>
			</footer>
		</div>
		<?php
	}

	private static function navigation( string $active ): void {
		$groups = array(
			__( 'Overview', 'nakhostin-performance-engine' ) => array( AdminPage::SLUG, PerformanceAdminPage::SLUG, DiagnosticsAdminPage::SLUG ),
			__( 'Optimization', 'nakhostin-performance-engine' ) => array( CacheAdminPage::SLUG, OptimizationAdminPage::SLUG, DOMAdminPage::SLUG, CSSAdminPage::SLUG, JavaScriptAdminPage::SLUG, ComponentsAdminPage::SLUG ),
			__( 'Integrations', 'nakhostin-performance-engine' ) => array( 'npe-woocommerce', 'npe-elementor', 'npe-litespeed' ),
			__( 'Settings', 'nakhostin-performance-engine' ) => array( AdminPage::SETTINGS_SLUG, LogsAdminPage::SLUG ),
		);
		$known = array_merge( ...array_values( $groups ) );
		$groups[ __( 'More', 'nakhostin-performance-engine' ) ] = array_diff( array_keys( self::$pages ), $known );
		foreach ( $groups as $label => $slugs ) {
			$available = array_filter( $slugs, static function ( string $slug ): bool {
				return isset( self::$pages[ $slug ] ) && current_user_can( self::$pages[ $slug ]['capability'] );
			} );
			if ( ! $available ) {
				continue;
			}
			echo '<p class="npe-nav-group">' . esc_html( $label ) . '</p><ul>';
			foreach ( $available as $slug ) {
				?>
				<li><a href="<?php echo esc_url( self::url( $slug ) ); ?>" <?php echo $active === $slug ? 'aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( self::icon( $slug ) ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( self::$pages[ $slug ]['label'] ); ?>
				</a></li>
				<?php
			}
			echo '</ul>';
		}
	}

	private static function url( string $slug ): string {
		return admin_url( 'admin.php?page=' . $slug );
	}

	private static function icon( string $slug ): string {
		$icons = array(
			AdminPage::SLUG             => 'dashicons-dashboard',
			AdminPage::SETTINGS_SLUG    => 'dashicons-admin-generic',
			PerformanceAdminPage::SLUG  => 'dashicons-chart-area',
			DiagnosticsAdminPage::SLUG  => 'dashicons-heart',
			CacheAdminPage::SLUG        => 'dashicons-database',
			OptimizationAdminPage::SLUG => 'dashicons-performance',
			DOMAdminPage::SLUG          => 'dashicons-networking',
			CSSAdminPage::SLUG          => 'dashicons-art',
			JavaScriptAdminPage::SLUG   => 'dashicons-editor-code',
			ComponentsAdminPage::SLUG   => 'dashicons-screenoptions',
			LogsAdminPage::SLUG         => 'dashicons-list-view',
		);
		return $icons[ $slug ] ?? 'dashicons-admin-plugins';
	}
}
