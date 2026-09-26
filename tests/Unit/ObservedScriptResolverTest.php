<?php
/** Rendered script-to-handle matching tests. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\JavaScript\ObservedScriptResolver;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use PHPUnit\Framework\TestCase;

final class ObservedScriptResolverTest extends TestCase {
	public function test_only_rendered_script_paths_become_page_roots(): void {
		$assets = array(
			new ScriptAsset( array( 'handle' => 'page', 'src' => '/assets/page.js?ver=1', 'enqueued' => true ) ),
			new ScriptAsset( array( 'handle' => 'other', 'src' => '/assets/other.js', 'enqueued' => true ) ),
			new ScriptAsset( array( 'handle' => 'inline-config', 'localized' => true, 'enqueued' => true ) ),
		);
		$scripts = array( array( 'src' => 'https://example.com/assets/page.js' ) );

		$this->assertSame( array( 'page', 'inline-config' ), ( new ObservedScriptResolver() )->resolve( $assets, $scripts ) );
	}
}
