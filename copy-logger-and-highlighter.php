<?php
/**
 * Plugin Name: Copy Logger and Highlighter
 * Plugin URI: http://whoischris.com
 * Description: Every time any user copies text on your website it will be logged, optionally highlight popular text for other users.  Manage logs and add snippets to copies.
 * Version: 1.0
 * Author: Chris Flannagan
 * Author URI: http://whoischris.com
 * License: GPL2
 */

global $clph_db_version;
$clph_db_version = '1.1';

if ( ! class_exists( 'CPLH' ) ) {
	class CPLH {
		/**
		 * Construct the plugin object
		 */
		public function __construct() {
			// register actions
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_menu', array( &$this, 'add_data_page' ) );
			add_action( 'admin_menu', array( &$this, 'admin_enqueue' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'logger_enqueue' ) );
			add_action( 'init', array( &$this, 'cplh_init' ) );

			// Setup AJAX points (until we potentially move to REST)
			add_action( 'wp_ajax_cplh_save_copied_selection', array( &$this, 'parse_ajax' ) );
			add_action( 'wp_ajax_nopriv_cplh_save_copied_selection', array( &$this, 'parse_ajax' ) );

			add_action( 'wp_ajax_cplh_get_copied_logs', array( &$this, 'parse_admin_ajax' ) );
			add_action( 'wp_ajax_cplh_del_copied_logs', array( &$this, 'del_admin_ajax' ) );

			//Add our highlights to the content
			add_filter( 'the_content', array( &$this, 'add_highlights' ), 20 );
		}

		/**
		 * Activate the plugin
		 */
		public static function activate() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'cplh_logger';

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				  userip varchar(25) DEFAULT '0.0.0.0' NOT NULL,
				  wpuserid mediumint(9) DEFAULT '0' NOT NULL,
				  postid mediumint(9) DEFAULT '0' NOT NULL,
				  url varchar(255) NOT NULL,
				  highlighted text NOT NULL,
				  UNIQUE KEY id (id)
				) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		} // END public static function activate

		/**
		 * Deactivate the plugin
		 */
		public static function deactivate() {
			// Do nothing
		} // END public static function deactivate

		/**
		 * Initiate admin settings
		 */
		public function admin_init()
		{
			//do nothing
		}

		public function settings_callback() {
			echo 'Manage Copy Logger Settings';
		}

		public function add_data_page() {
			//Place a link to our reports page on the Wordpress menu
			add_menu_page( 'Copy Logger', 'Copy Logger', 'manage_options', 'cplh-data', array( $this, 'cplh_data_page' ) );

		}

		public function cplh_data_page() {
			//Include our settings page template
			include(sprintf("%s/views/cplh-data.php", dirname(__FILE__)));

		}

		public function admin_enqueue() {
			wp_enqueue_script( 'logger-admin-js', plugin_dir_url( __FILE__ ) . 'js/logger-admin.js', array( 'jquery' ), '1.0', true );


			global $post;
			$current_post_id = 0;
			if( is_single() || is_page() ) {
				$current_post_id = $post->ID;
			}
			$script_vars = array(
				'wp_ajax'               => admin_url( 'admin-ajax.php' ),
				// Let's go wild with console.methods() IF we're debugging
				'hi_roy'                => true
			);
			wp_localize_script( 'logger-admin-js', 'CPLH_VARS', $script_vars );

			wp_enqueue_style('wp-color-picker');
			wp_enqueue_script('iris', admin_url('js/iris.min.js'),array('jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch'), false, 1);
			wp_enqueue_script('wp-color-picker', admin_url('js/color-picker.min.js'), array('iris'), false,1);
			$colorpicker_l10n = array('clear' => __('Clear'), 'defaultString' => __('Default'), 'pick' => __('Select Color'));
			wp_localize_script( 'wp-color-picker', 'wpColorPickerL10n', $colorpicker_l10n );

		}

		public function logger_enqueue() {
			wp_enqueue_script( 'logger-js', plugin_dir_url( __FILE__ ) . 'js/logger.js', array( 'jquery' ), '1.0', true );
			$attach_message = '';
			if ( get_option( "cplh_attach_message" ) !== false ) {
				$attach_message = get_option( "cplh_attach_message" );
			}

			global $post;
			$current_post_id = 0;
			if( is_single() || is_page() ) {
				$current_post_id = $post->ID;
			}
			$script_vars = array(
				'wp_ajax'               => admin_url( 'admin-ajax.php' ),
				// for front-facing safety
				'debug_on'              => defined( 'WP_DEBUG' ) && true === WP_DEBUG,
				// Let's go wild with console.methods() IF we're debugging
				'hi_roy'                => true,
				'cplh_nonce_shall_pass' => wp_create_nonce( 'cplh_nonce' ),
				// MB: I don't think we should check this as front-end + caching will often result in false negatives
				'post_id_maybe'         => $current_post_id,
				'attach_message'		=> $attach_message
			);
			wp_localize_script( 'logger-js', 'CPLH_VARS', $script_vars );
		}

		public function parse_admin_ajax() {
			if ( empty( $_POST['data'] ) ) {
				wp_send_json_error( 'CLPH: No data, no soup for you' );
			} else {
				global $wpdb;
				$table_name = $wpdb->prefix . 'cplh_logger';
				if( ! empty( $_POST['pid'] ) ) {
					$table_name .= ' WHERE postid="' . intval( $_POST['pid'] ) . '" ';
				}
				$response_logs = $wpdb->get_results(
					"
					SELECT *
					FROM $table_name
					ORDER BY time DESC
					"
				);

				$response = array(
					'success' => true,
					'data'    => $response_logs
				);


				wp_send_json( $response );
			}
		}

		function del_admin_ajax() {
			/* Admin ability to check & delete logs */
			global $wpdb;
			$table_name = $wpdb->prefix . 'cplh_logger';
			$to_delete = explode( ',', $_POST['ids'] );
			$deleted = '';
			foreach( $to_delete as $del ) {
				$wpdb->delete( $table_name, array( 'id' => $del ), array( '%d' ) );
				$deleted .= $del . ',';
			}

			$response = array(
				'success' => true,
				'data' => $deleted
			);

			wp_send_json( $response );
		}

		public function parse_ajax() {

			if ( empty( $_POST['data'] ) ) {
				wp_send_json_error( 'CLPH: No data, no soup for you' );
			}

			if ( empty( $_POST['data']['texts'] ) ) {
				wp_send_json_error( 'CLPH: No text found' );
			}


			if ( empty( $_POST['data']['curr_url'] ) ) {
				$url_saved = false; // Simply to make PHP happy. Never actually gets used because we exit at next line
				wp_send_json_error( 'CLPH: Where are you??' );
			} else {
				$url_saved = $_POST['data']['curr_url'];
			}

			if ( empty( $_POST['data']['post_id'] ) ) {
				$post_id_saved = false; // Simply to make PHP happy. Never actually gets used because we exit at next line
				wp_send_json_error( 'CLPH: problem with post id, should be 0 if not a post/page' );
			} else {
				$post_id_saved = $_POST['data']['post_id'];
			}


			$received_texts = $_POST['data']['texts'];

			if ( ! is_array( $received_texts ) ) {
				wp_send_json_error( 'CLPH: The Texts should be an array' );
			}


			// Getting the user's IP
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			// Is this an existing WP user? Might as well save their info
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();

				if ( ! ( $current_user instanceof WP_User ) ) {
					$user = 0;
				} else {
					$user = $current_user->ID;
				}
			} else {
				$user = 0;
			}


			$errors = array();
			foreach ( $received_texts as $received_text ) {
				// @todo: should probably filter first. Wasn't sure if we only accepted pure text or HTML as well

				$saved = $this->save_selection( $received_text, $url_saved, $post_id_saved, $ip, $user );

				if ( ! $saved ) {
					$errors[] = esc_html( 'Error saving ' . $received_text );
				}
			}

			if ( count( $errors ) > 0 ) {
				// Something went wrong while trying to save
				$response = array(
					'success' => false,
					'data'    => $errors,
				);
				wp_send_json_error( $response );

			} else {
				// We're golden! Success!
				$response = array(
					'success' => true,
					'data'    => 'saved!',
				);

				wp_send_json( $response );
			}
		}

		public function save_selection( $selection, $url_saved, $post_id_saved, $ip, $user ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'cplh_logger';

			//  perform some checks/escapes
			//  if($problem) {
			//  	return false;
			//  }

			$wpdb->insert(
				$table_name,
				array(
					'time' => current_time( 'mysql' ),
					'userip' => $ip,
					'wpuserid' => $user,
					'postid' => $post_id_saved,
					'url' => $url_saved,
					'highlighted' => $selection
				)
			);

			return true;
		}

		//Sanitizers
		public function cplh_hexcolor_sanitize( $new_value, $old_value ) {
			$new_value = sanitize_hex_color( $new_value );
			return $new_value;
		}
		public function cplh_init() {
			add_filter( 'pre_update_option_cplh_highlight_color', array( &$this, 'cplh_hexcolor_sanitize' ), 10, 2 );
		}

		//Front end output
		public function add_highlights( $content ) {
			global $post;

			$show_pages = false;
			$show_posts = false;
			$admin_only = false;
			if ( get_option( "cplh_highlight_posts" ) !== false && get_option( "cplh_highlight_posts" ) ) {
				$show_posts = true;
			}
			if ( get_option( "cplh_highlight_pages" ) !== false && get_option( "cplh_highlight_pages" ) ) {
				$show_pages = true;
			}
			if ( get_option( "cplh_highlight_admin" ) !== false && get_option( "cplh_highlight_admin" ) ) {
				$admin_only = true;
			}

			$continue = true;
			if( is_page() && ! $show_pages ) {
				$continue = false;
			}
			if( is_single() && ! $show_posts ) {
				$continue = false;
			}
			if( ! current_user_can( 'manage_options' ) && $admin_only ) {
				$continue = false;
			}

			if( $continue ) {
				require_once( 'inc/chars.php' );
				$HTML401NamedToNumeric = ret_chars();
				global $post;
				$current_post_id = 0;
				if( is_single() || is_page() ) {
					$current_post_id = $post->ID;
				}

				global $wpdb;
				$table_name = $wpdb->prefix . 'cplh_logger';
				$response_logs = $wpdb->get_results(
					"
						SELECT *
						FROM $table_name
						WHERE postid='$current_post_id'
						"
				);

				$highlight_color = "#fffa00";
				if( get_option( "cplh_highlight_color" ) !== false  ) {
					$highlight_color = get_option( "cplh_highlight_color" );
				}

				foreach( $response_logs as $log ) {
					$encoded_hl = strtr( htmlentities( stripslashes( $log->highlighted ), ENT_QUOTES ), $HTML401NamedToNumeric );
					$slashless_hl = htmlentities( stripslashes( $log->highlighted ), ENT_NOQUOTES );
					$content = str_replace( $encoded_hl, '<span style="background-color: ' . $highlight_color .'">' . $encoded_hl . '</span>', $content );
					$content = str_replace( $slashless_hl, '<span style="background-color: ' . $highlight_color .'">' . $slashless_hl . '</span>', $content );
				}
			}
			return $content;
		}

	}
}

if ( class_exists( 'CPLH' ) ) {
	// Installation and uninstallation hooks
	register_activation_hook( __FILE__, array( 'CPLH', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'CPLH', 'deactivate' ) );

	// instantiate the plugin class
	$CPLH = new CPLH();
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color )
			return '';

		// 3 or 6 hex digits, or the empty string.
		if ( preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) )
			return $color;

		return null;
	}
}
