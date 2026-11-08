<?php
/**
 * Class autoloader.
 *
 * @package FraudNova
 */

namespace FraudNova\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Maps FraudNova\WordPress\Sub\Class_Name to includes/sub/class-class-name.php.
 */
class Autoloader {

	const PREFIX = 'FraudNova\\WordPress\\';

	/**
	 * Directory classes are loaded from.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Absolute path of the includes directory.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		$file = self::file_for( $class_name );
		if ( null !== $file && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolve the file for a class.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return string|null Path, or null for classes outside the plugin namespace.
	 */
	public static function file_for( string $class_name ): ?string {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return null;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$parts    = explode( '\\', strtolower( str_replace( '_', '-', $relative ) ) );
		$name     = array_pop( $parts );
		$dir      = '' === self::$base_dir ? FRAUDNOVA_PATH . 'includes/' : self::$base_dir;

		return $dir . ( $parts ? implode( '/', $parts ) . '/' : '' ) . 'class-' . $name . '.php';
	}
}
