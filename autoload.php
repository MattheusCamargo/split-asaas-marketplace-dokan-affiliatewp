<?php
/**
 * Plugin autoload files
 *
 * @package WooAsaas
 */

namespace WC_Asaas;

/**
 * Autoload plugin files using WordPress naming conventions
 *
 * Load just proceed with auload just if the class belongs to WC_Asaas
 * namespace
 *
 * @link https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/#naming-conventions
 * @link http://php.net/manual/language.namespaces.rules.php
 * @link http://php.net/manual/function.spl-autoload-register.php
 *
 * @param string $class_name The class relative name.
 */
function autoload( $class_name ) {
	if ( false === strpos( $class_name, __NAMESPACE__ ) ) {
		return;
	}

	/* Remove namespace from class name */
	$class_file = str_replace( __NAMESPACE__ . '\\', '', $class_name );

	/* Convert class name format to file name format */
	$class_file = strtolower( $class_file );
	$class_file = str_replace( '_', '-', $class_file );

	/* Convert sub-namespaces into directories */
	$class_path = explode( '\\', $class_file );
	$class_file = array_pop( $class_path );
	$class_path = implode( '/', $class_path );
	
	$file_path = __DIR__ . '/includes/' . ($class_path ? rtrim($class_path, '/') . '/' : '') . 'class-' . $class_file . '.php';
	if (!file_exists($file_path)) {
		// Tenta encontrar em subdiretórios comuns
		$subdirs = array('admin/settings', 'admin', 'gateway', 'integration', 'split/admin/settings', 'split/admin');
		foreach ($subdirs as $subdir) {
			$alt_path = __DIR__ . '/includes/' . $subdir . '/class-' . $class_file . '.php';
			if (file_exists($alt_path)) {
				$file_path = $alt_path;
				break;
			}
		}
	}
	
	require_once $file_path;
}
spl_autoload_register( __NAMESPACE__ . '\autoload' );
