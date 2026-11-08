<?php
/**
 * Autoloader tests.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

use FraudNova\WordPress\Autoloader;
use FraudNova\WordPress\Plugin;

/**
 * @covers \FraudNova\WordPress\Autoloader
 */
class AutoloaderTest extends TestCase {

	public function test_maps_namespaced_class_to_wp_style_file() {
		$this->assertSame(
			FRAUDNOVA_PATH . 'includes/admin/class-settings-page.php',
			Autoloader::file_for( 'FraudNova\WordPress\Admin\Settings_Page' )
		);
		$this->assertStringEndsWith(
			'includes/integrations/class-contact-form-7.php',
			Autoloader::file_for( 'FraudNova\WordPress\Integrations\Contact_Form_7' )
		);
	}

	public function test_ignores_foreign_namespaces() {
		$this->assertNull( Autoloader::file_for( 'Other\Thing' ) );
	}

	public function test_plugin_is_a_singleton() {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}
}
