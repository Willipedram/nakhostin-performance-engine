<?php
/**
 * Components administration output tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\ComponentsAdminPage;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use PHPUnit\Framework\TestCase;

final class ComponentsAdminPageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']    = array();
		$GLOBALS['npe_test_can_manage'] = true;
	}

	public function test_custom_component_form_is_nonce_protected_and_localization_safe(): void {
		$page = $this->page();

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'name="component[selectors]"', $output );
		$this->assertStringContainsString( 'CSS dependencies', $output );
		$this->assertStringContainsString( 'CORE_HEADER', $output );
	}

	public function test_unauthorized_user_cannot_view_components(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );

		$this->page()->render();
	}

	private function page(): ComponentsAdminPage {
		return new ComponentsAdminPage(
			new ComponentRegistry( new ComponentStorage(), new BundleIndex() ),
			new Capabilities()
		);
	}
}
