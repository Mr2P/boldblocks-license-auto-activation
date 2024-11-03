<?php
/**
 * Plugin Name: Boldblocks - license auto activation
 * Description: Autoactivate license via wp-config.php
 * Version:     1.0.0
 * Author:      mr2p
 *
 * Adapted from https://gist.github.com/vovafeldman/f28a46958d8f648cf3f62c7a3a975a8e
 */

if ( ! class_exists( 'BoldBlocks_License_Auto_Activator' ) ) {
	class BoldBlocks_License_Auto_Activator {
		private $shortcode;
		private $license_key;

		public function __construct( $shortcode, $license_key ) {
			$this->shortcode   = $shortcode;
			$this->license_key = $license_key;
		}

		public function run() {
			add_action( 'admin_init', [ $this, 'license_key_auto_activation' ], 999 );
		}

		public function license_key_auto_activation() {
			$fs = false;
			$this->debug_notices( 'license_key_auto_activation function started' );

			if ( function_exists( $this->shortcode ) ) {
				$fs = ( $this->shortcode )();
			}

			if ( empty( $fs ) ) {
				$this->debug_notices( 'fs is empty ' );
				return;
			}

			if ( false === $fs->has_api_connectivity() ) {
				$this->debug_notices( 'Error: no API connectivity' );
				return;
			}

			if ( $fs->is_registered() ) {
				$this->debug_notices( 'Notice: The user already opted-in to Freemius' );
			}

			$option_key = "{$this->shortcode}_auto_license_activation";
			$this->debug_notices( "$option_key " );

			try {
				$next_page = $fs->activate_migrated_license( $this->license_key );
			} catch ( Exception $e ) {
				$this->debug_notices( 'Error: ' . $e->getMessage() );
				update_option( $option_key, 'unexpected_error' );
				return;
			}

			if ( $fs->can_use_premium_code() ) {
				update_option( $option_key, 'done' );
				$this->debug_notices( 'Success: license key install is done.' );

				if ( is_string( $next_page ) ) {
					fs_redirect( $next_page );
				}
			} else {
				$this->debug_notices( 'Error: license key install failed ' );
				update_option( $option_key, 'failed' );
			}
		}

		private function debug_notices( $message ) {
			if ( defined( 'BOLDBLOCKS_DEBUG' ) && ! empty( BOLDBLOCKS_DEBUG ) ) {
				error_log( $message ); // phpcs:ignore
			}
		}
	}
}

function boldblocks_license_autoactivate() {
	$fs_shortcodes = [];

	if ( defined( 'MFB_LICENSE_KEY' ) && ! empty( MFB_LICENSE_KEY ) ) {
		$fs_shortcodes['MetaFieldBlock\mfb_fs'] = MFB_LICENSE_KEY;
	}

	if ( defined( 'CBB_LICENSE_KEY' ) && ! empty( CBB_LICENSE_KEY ) ) {
		$fs_shortcodes['BoldBlocks\cbb_fs'] = CBB_LICENSE_KEY;
	}

	foreach ( $fs_shortcodes as $fs_shortcode => $license_key ) {
		( new BoldBlocks_License_Auto_Activator( $fs_shortcode, $license_key ) )->run();
	}
}
add_action( 'plugins_loaded', 'boldblocks_license_autoactivate' );
