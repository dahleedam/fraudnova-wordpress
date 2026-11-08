<?php
/**
 * Leak guard: forbidden strings must never appear in the repo.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress\Tests;

/**
 * Scans files and commit messages for forbidden strings.
 */
class LeakGuardTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Forbidden strings, assembled from fragments.
	 *
	 * @return string[]
	 */
	private function forbidden(): array {
		return array(
			'IPQ' . 'ualityScore',
			'IP' . 'QS',
			'upstream ' . 'key',
			'key ' . 'pool',
			'Cla' . 'ude',
			'Anth' . 'ropic',
			'Co-Authored' . '-By',
			'Generated ' . 'with',
		);
	}

	public function test_no_file_contains_a_forbidden_string() {
		$root     = dirname( __DIR__ );
		$skip_dir = array( 'vendor', '.git', 'dist' );
		$skip     = array( __FILE__, $root . '/composer.lock' );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
				function ( $current ) use ( $skip_dir ) {
					return ! ( $current->isDir() && in_array( $current->getFilename(), $skip_dir, true ) );
				}
			)
		);

		$hits = array();
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || in_array( $file->getPathname(), $skip, true ) ) {
				continue;
			}
			$contents = (string) file_get_contents( $file->getPathname() );
			foreach ( $this->forbidden() as $needle ) {
				if ( false !== stripos( $contents, $needle ) ) {
					$hits[] = $file->getPathname() . ' contains ' . $needle;
				}
			}
		}

		$this->assertSame( array(), $hits );
	}

	public function test_commit_messages_are_clean() {
		$root = dirname( __DIR__ );
		if ( ! is_dir( $root . '/.git' ) ) {
			$this->markTestSkipped( 'No .git directory.' );
		}
		$log = (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' log --format=%B 2>/dev/null' );
		foreach ( $this->forbidden() as $needle ) {
			$this->assertFalse( stripos( $log, $needle ), "Commit history contains {$needle}" );
		}
	}
}
