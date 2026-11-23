<?php
/**
 * Shared field and metric renderers for NPE administration.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class AdminComponents {
	public static function field( string $type, string $group, string $key, $value, string $label, string $help, array $options = array() ): void {
		$name = Settings::OPTION . '[' . $group . '][' . $key . ']';
		$id = 'npe-field-' . $group . '-' . $key . ( 'radio' === $type ? '-' . $options[0] : '' );
		$help_id = $id . '-help';
		?>
		<div class="npe-setting npe-setting--<?php echo esc_attr( $type ); ?>">
			<?php if ( 'toggle' === $type ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
			<?php endif; ?>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( 'toggle' === $type ) : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="checkbox" role="switch" name="<?php echo esc_attr( $name ); ?>" value="1" aria-describedby="<?php echo esc_attr( $help_id ); ?>" <?php checked( $value ); ?>>
			<?php elseif ( 'radio' === $type ) : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $options[0] ); ?>" aria-describedby="<?php echo esc_attr( $help_id ); ?>" <?php checked( $value, $options[0] ); ?>>
			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" rows="3" dir="ltr" name="<?php echo esc_attr( $name ); ?>" aria-describedby="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $value ); ?></textarea>
			<?php else : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $type ); ?>" dir="ltr" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" aria-describedby="<?php echo esc_attr( $help_id ); ?>" <?php if ( 'number' === $type ) : ?>min="<?php echo esc_attr( $options[0] ); ?>" max="<?php echo esc_attr( $options[1] ); ?>"<?php endif; ?>>
			<?php endif; ?>
			<details class="npe-setting-help">
				<summary aria-label="<?php /* translators: %s: setting label. */ echo esc_attr( sprintf( __( 'Help for %s', 'nakhostin-performance-engine' ), $label ) ); ?>"><span aria-hidden="true">?</span></summary>
				<p id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			</details>
		</div>
		<?php
	}

	public static function metric( string $label, string $value, string $description ): void { ?><section class="npe-metric" role="listitem"><h2><?php echo esc_html( $label ); ?></h2><strong class="npe-metric-value npe-technical" dir="ltr"><?php echo esc_html( $value ); ?></strong><p><?php echo esc_html( $description ); ?></p></section><?php }
}
