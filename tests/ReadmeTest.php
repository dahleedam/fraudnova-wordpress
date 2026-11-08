<?php
/**
 * Readme, header and changelog consistency.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

/**
 * Keeps the plugin header, readme.txt and CHANGELOG.md in agreement.
 */
class ReadmeTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Read a repo file.
	 *
	 * @param string $name File name relative to the repo root.
	 * @return string
	 */
	private function read( string $name ): string {
		$path = dirname( __DIR__ ) . '/' . $name;
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	/**
	 * A "Field: value" line from a header block.
	 *
	 * @param string $contents File contents.
	 * @param string $field    Field name.
	 * @return string
	 */
	private function field( string $contents, string $field ): string {
		$this->assertSame( 1, preg_match( '/^[\s\*]*' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m', $contents, $m ), "Missing field {$field}" );
		return $m[1];
	}

	public function test_versions_agree() {
		$plugin = $this->read( 'fraudnova.php' );
		$this->assertSame( 1, preg_match( "/define\(\s*'FRAUDNOVA_VERSION',\s*'([^']+)'\s*\)/", $plugin, $m ) );
		$constant = $m[1];

		$this->assertSame( 1, preg_match( '/^## \[(\d+\.\d+\.\d+)\]/m', $this->read( 'CHANGELOG.md' ), $c ) );

		$header = $this->field( $plugin, 'Version' );
		$this->assertSame( $header, $constant );
		$this->assertSame( $header, $this->field( $this->read( 'readme.txt' ), 'Stable tag' ) );
		$this->assertSame( $header, $c[1] );
	}

	public function test_pot_version_matches_plugin_version() {
		$version = $this->field( $this->read( 'fraudnova.php' ), 'Version' );
		$this->assertStringContainsString( '"Project-Id-Version: FraudNova ' . $version . '\\n"', $this->read( 'languages/fraudnova.pot' ) );
		$this->assertStringContainsString( 'FraudNova ' . $version, $this->read( 'composer.json' ) );
	}

	public function test_header_fields_match_readme() {
		$plugin = $this->read( 'fraudnova.php' );
		$readme = $this->read( 'readme.txt' );
		foreach ( array( 'Requires at least', 'Tested up to', 'Requires PHP' ) as $field ) {
			$this->assertSame( $this->field( $plugin, $field ), $this->field( $readme, $field ), $field );
		}
	}

	public function test_readme_has_required_sections() {
		$readme = $this->read( 'readme.txt' );
		foreach ( array( '== Description ==', '== Installation ==', '== Frequently Asked Questions ==', '== Screenshots ==', '== Changelog ==', '== Upgrade Notice ==' ) as $section ) {
			$this->assertStringContainsString( $section, $readme );
		}

		$this->assertSame( 1, preg_match( '/^= External services =\s*$(.*?)(?=^==? )/ms', $readme, $m ), 'Missing = External services = heading' );
		$this->assertStringContainsString( 'https://fraudnova.com/privacy', $m[1] );
		$this->assertStringContainsString( 'https://fraudnova.com/terms', $m[1] );
	}

	public function test_readme_short_description_is_under_150_chars() {
		$readme = $this->read( 'readme.txt' );
		$parts  = preg_split( '/\R\R/', $readme );
		// Block 0 is the header; the short description is the first paragraph after it.
		$this->assertIsArray( $parts );
		$short = trim( (string) $parts[1] );
		$this->assertNotSame( '', $short );
		$this->assertStringStartsNotWith( '==', $short );
		$this->assertLessThanOrEqual( 150, strlen( $short ), $short );
	}
}
