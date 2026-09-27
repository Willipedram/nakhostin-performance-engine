<?php
/**
 * Component registry administration screen.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Core\Capabilities;

final class ComponentsAdminPage {
	public const SLUG = 'npe-components';
	public const ACTION = 'npe_save_component';

	/** @var ComponentRegistry */
	private $registry;

	/** @var Capabilities */
	private $capabilities;

	public function __construct( ComponentRegistry $registry, Capabilities $capabilities ) {
		$this->registry     = $registry;
		$this->capabilities = $capabilities;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			AdminPage::SLUG,
			__( 'Components', 'nakhostin-performance-engine' ),
			__( 'Components', 'nakhostin-performance-engine' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function handle_save(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage components.', 'nakhostin-performance-engine' ) );
		}

		check_admin_referer( self::ACTION );
		$input = isset( $_POST['component'] ) && is_array( $_POST['component'] )
			? wp_unslash( $_POST['component'] )
			: array();

		try {
			$component = new ComponentDefinition(
				array(
					'id'                        => $input['id'] ?? '',
					'name'                      => $input['name'] ?? '',
					'selectors'                 => $this->lines( $input['selectors'] ?? '' ),
					'css_dependencies'          => $this->lines( $input['css_dependencies'] ?? '' ),
					'javascript_dependencies'   => $this->lines( $input['javascript_dependencies'] ?? '' ),
					'dynamic_states'            => $this->lines( $input['dynamic_states'] ?? '' ),
					'cache_behavior'            => $input['cache_behavior'] ?? 'shared',
					'invalidation_dependencies' => $this->lines( $input['invalidation_dependencies'] ?? '' ),
					'integration_owner'         => 'custom',
				)
			);
			$invalidated = $this->registry->register_custom( $component );
			$this->redirect( 'saved', count( $invalidated ) );
		} catch ( InvalidArgumentException $exception ) {
			$this->redirect( 'invalid', 0 );
		}
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view components.', 'nakhostin-performance-engine' ) );
		}

		$components = $this->registry->all();
		$custom     = $this->registry->custom();
		$usage      = $this->registry->usage();
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'Component Intelligence', 'nakhostin-performance-engine' ); ?></h1>
			<?php $this->render_notice(); ?>
			<div class="npe-card">
				<h2><?php echo esc_html__( 'Register Custom Component', 'nakhostin-performance-engine' ); ?></h2>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php
					$this->field(
						'id',
						__( 'Component ID', 'nakhostin-performance-engine' ),
						'CUSTOM_PRODUCT_CARD'
					);
					?>
					<?php $this->field( 'name', __( 'Name', 'nakhostin-performance-engine' ), '' ); ?>
					<?php $this->textarea( 'selectors', __( 'DOM selectors', 'nakhostin-performance-engine' ) ); ?>
					<?php
					$this->textarea(
						'css_dependencies',
						__( 'CSS dependencies or sources', 'nakhostin-performance-engine' )
					);
					$this->textarea(
						'javascript_dependencies',
						__( 'JavaScript dependencies or sources', 'nakhostin-performance-engine' )
					);
					?>
					<?php $this->textarea( 'dynamic_states', __( 'Dynamic states', 'nakhostin-performance-engine' ) ); ?>
					<?php
					$this->textarea(
						'invalidation_dependencies',
						__( 'Invalidation dependencies', 'nakhostin-performance-engine' )
					);
					?>
					<label for="npe-cache-behavior">
						<?php echo esc_html__( 'Cache behavior', 'nakhostin-performance-engine' ); ?>
					</label>
					<select id="npe-cache-behavior" name="component[cache_behavior]">
						<option value="shared"><?php echo esc_html__( 'Shared', 'nakhostin-performance-engine' ); ?></option>
						<option value="private"><?php echo esc_html__( 'Private', 'nakhostin-performance-engine' ); ?></option>
						<option value="uncacheable">
							<?php echo esc_html__( 'Uncacheable', 'nakhostin-performance-engine' ); ?>
						</option>
					</select>
					<p class="description">
						<?php
						echo esc_html__(
							'Enter one selector, dependency, or state per line.',
							'nakhostin-performance-engine'
						);
						?>
					</p>
					<?php submit_button( __( 'Save Component', 'nakhostin-performance-engine' ) ); ?>
				</form>
			</div>
			<?php
			$this->render_table(
				__( 'Detected and Registered Components', 'nakhostin-performance-engine' ),
				$components,
				$usage
			);
			?>
			<?php $this->render_table( __( 'Custom Components', 'nakhostin-performance-engine' ), $custom, $usage ); ?>
		</div>
		<?php
	}

	private function render_table( string $title, array $components, array $usage ): void {
		?>
		<div class="npe-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Component', 'nakhostin-performance-engine' ); ?></th>
					<th><?php echo esc_html__( 'Usage', 'nakhostin-performance-engine' ); ?></th>
					<th><?php echo esc_html__( 'CSS dependencies', 'nakhostin-performance-engine' ); ?></th>
					<th><?php echo esc_html__( 'JS dependencies', 'nakhostin-performance-engine' ); ?></th>
					<th><?php echo esc_html__( 'Page types', 'nakhostin-performance-engine' ); ?></th>
					<th><?php echo esc_html__( 'Signature', 'nakhostin-performance-engine' ); ?></th>
				</tr></thead><tbody>
				<?php foreach ( $components as $component ) : ?>
					<?php $this->render_row( $component, $usage[ $component->id() ] ?? array() ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_row( ComponentDefinition $component, array $usage ): void {
		$data = $component->to_array();
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( $data['name'] ); ?></strong><br>
				<code><?php echo esc_html( $data['id'] ); ?></code>
			</td>
			<td><?php echo esc_html( (string) ( $usage['count'] ?? 0 ) ); ?></td>
			<td><?php echo esc_html( implode( ', ', $data['css_dependencies'] ) ); ?></td>
			<td><?php echo esc_html( implode( ', ', $data['javascript_dependencies'] ) ); ?></td>
			<td><?php echo esc_html( implode( ', ', $usage['page_types'] ?? array() ) ); ?></td>
			<td><code><?php echo esc_html( substr( $component->signature(), 0, 16 ) ); ?></code></td>
		</tr>
		<?php
	}

	private function field( string $id, string $label, string $placeholder ): void {
		printf(
			'<p><label for="npe-component-%1$s">%2$s</label><br>'
				. '<input class="regular-text" id="npe-component-%1$s" name="component[%1$s]" '
				. 'type="text" placeholder="%3$s" required></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $placeholder )
		);
	}

	private function textarea( string $id, string $label ): void {
		printf(
			'<p><label for="npe-component-%1$s">%2$s</label><br>'
				. '<textarea class="large-text code" id="npe-component-%1$s" '
				. 'name="component[%1$s]" rows="3"></textarea></p>',
			esc_attr( $id ),
			esc_html( $label )
		);
	}

	private function lines( $value ): array {
		return preg_split( '/[\r\n,]+/', (string) $value ) ?: array();
	}

	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted notice state.
		$status = isset( $_GET['npe_component_status'] ) ? sanitize_key( wp_unslash( $_GET['npe_component_status'] ) ) : '';
		if ( ! in_array( $status, array( 'saved', 'invalid' ), true ) ) {
			return;
		}

		$message = 'saved' === $status
			? __( 'Component saved. Dependent bundle plans were invalidated where necessary.', 'nakhostin-performance-engine' )
			: __( 'The component definition is invalid.', 'nakhostin-performance-engine' );
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( 'saved' === $status ? 'success' : 'error' ),
			esc_html( $message )
		);
	}

	private function redirect( string $status, int $invalidated ): void {
		$url = add_query_arg(
			array(
				'page'                 => self::SLUG,
				'npe_component_status' => $status,
				'invalidated'          => $invalidated,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
