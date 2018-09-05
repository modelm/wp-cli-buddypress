<?php
namespace Buddypress\CLI\Command;

use WP_CLI;
use Scaffold_Command;

class BuddyPress_Scaffold_Command extends Scaffold_Command {
	function prompt_if_files_will_be_overwritten( $file_name, $force ) {
		return parent::prompt_if_files_will_be_overwritten( $file_name, $force );
	}

	function log_whether_files_written( $files_written, $skip_message, $success_message ) {
		return parent::log_whether_files_written( $files_written, $skip_message, $success_message );
	}

	/**
	 * Gets the template path based on installation type.
	 */
	static function get_template_path( $template ) {
		$command_root = WP_CLI\Utils\phar_safe_path( dirname( __DIR__ ) );
		$template_path = "{$command_root}/templates/{$template}";
		if ( ! file_exists( $template_path ) ) {
			WP_CLI::error( "Couldn't find {$template}" );
		}
		return $template_path;
	}
}

/**
 * Generate code for plugin tests.
 *
 * @since 1.8
 */
class Scaffold extends BuddypressCommand {

	/**
	 * Plugin tests.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bp scaffold plugin-tests
	 *
	 * @subcommand plugin-tests
	 */
	public function plugin_tests( $args, $assoc_args ) {
		global $wp_filesystem;

		$Scaffold = new Buddypress_Scaffold_Command;
		$Scaffold->plugin_tests( $args, $assoc_args );

		$slug = $args[0];
		$target_dir = WP_PLUGIN_DIR . "/$slug";
		$tests_dir = "$target_dir/tests";
		$bin_dir = "$target_dir/bin";

		$to_copy = array(
			'install-bp-tests.sh' => $bin_dir,
			'bootstrap-buddypress.php' => $tests_dir,
		);
		foreach ( $to_copy as $file => $dir ) {
			$file_name = "$dir/$file";
			$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
			$should_write_file = $Scaffold->prompt_if_files_will_be_overwritten( $file_name, $force );
			if ( ! $should_write_file ) {
				continue;
			}
			$files_written[] = $file_name;
			$wp_filesystem->copy( $Scaffold::get_template_path( $file ), $file_name, true );
			if ( 'install-bp-tests.sh' === $file ) {
				if ( ! $wp_filesystem->chmod( "$dir/$file", 0755 ) ) {
					WP_CLI::warning( "Couldn't mark 'install-wp-tests.sh' as executable." );
				}
			}
		}
		$Scaffold->log_whether_files_written(
			$files_written,
			$skip_message = 'All BuddyPress test files were skipped.',
			$success_message = 'Created BuddyPress test files.'
		);
	}
}
