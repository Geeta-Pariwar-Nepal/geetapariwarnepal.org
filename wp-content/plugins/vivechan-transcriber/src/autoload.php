<?php
/**
 * PSR-4 style autoloader for the Vivechan\ namespace.
 */

defined('ABSPATH') || exit;

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Vivechan\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = VIVECHAN_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
