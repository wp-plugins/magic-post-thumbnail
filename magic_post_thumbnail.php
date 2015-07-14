<?php
/*
Plugin Name: Magic Post Thumbnail
Plugin URI: http://wordpress.org/plugins/magic-post-thumbnail/
Description: Automatically add a thumbnail for your posts. Retrieve first image from the database Google Image based on post title and add it as your featured thumbnail when you publish/update it.
Version: 2.3	
Author: Alexandre Gaboriau
Author URI: http://www.alexandregaboriau.fr/
Text Domain: 'mpt'
Domain Path: /languages


Copyright 2015 Alexandre Gaboriau (contact@alexandregaboriau.fr)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

class MPT_backoffice {

	public function __construct() {
	
		add_action( 'save_post', array( &$this, 'MPT_create_thumb' ) );
		
		add_action( 'admin_menu', array( &$this, 'MPT_menu' ) );
		
		register_activation_hook( __FILE__, array( &$this, 'MPT_default_values' ) );
		
		add_filter('plugin_action_links', array(&$this, 'MPT_add_settings_link'), 10, 2 );
		
		load_plugin_textdomain( 'mpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		add_action('admin_enqueue_scripts', array( &$this, 'MPT_admin_enqueues' ) ); // Plugin hook for adding CSS and JS files required for this plugin
		
		add_action( 'add_meta_boxes', array( &$this, 'MPT_add_custom_box' ) );
		add_action( 'save_post', array( &$this, 'MPT_save_postdata' ) );
		
		add_filter( 'bulk_actions-edit-post', array( &$this, 'MPT_add_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-page', array( &$this, 'MPT_add_bulk_actions' ) );
		add_action( 'admin_action_bulk_regenerate_thumbnails', array( &$this, 'MPT_bulk_action_handler' ) ); // Top drowndown
		
	}
	
	public function MPT_admin_enqueues() {
		
		if ( ( ! empty( $_POST['mpt'] ) || ! empty( $_REQUEST['ids'] ) ) && ( empty($_REQUEST['settings-updated']) || $_REQUEST['settings-updated'] != 'true' ) ) {
			wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'assets/js/jquery-ui/jquery-ui.js', __FILE__ ), array( 'jquery-ui-core' ) );
			wp_enqueue_style( 'style-jquery-ui', plugins_url( 'assets/js/jquery-ui/jquery-ui.css', __FILE__ ) );
			wp_enqueue_script( 'images-genration', plugins_url( 'assets/js/generation.js', __FILE__ ), array( 'jquery-ui-progressbar' ) );
		}
		wp_enqueue_script( 'tabs', plugins_url( 'assets/js/tabs.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_style( 'style-admin-mpt', plugins_url( 'assets/css/admin-style.css', __FILE__ ) );	
	}
	
	public function MPT_bulk_action_handler() {
		$ids = implode( ',', array_map( 'intval', $_REQUEST['post'] ) );
		wp_redirect(  'options-general.php?page=mpt&ids=' . $ids );
		exit();
	}
	
	
	public function MPT_add_bulk_actions( $actions ) {
?>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('select[name^="action"] option:last-child').before('<option value="bulk_regenerate_thumbnails"><?php echo esc_attr( __( 'Generate Magic Post thumbnail', 'mpt' ) ); ?></option>');
			});
		</script>
<?php
		return $actions;
	}
	
	
	private function MPT_Generate( $service, $url, $url_parameters ) {
		
		/* Retrieve 3 images as result */
		$url = add_query_arg( $url_parameters, $url );
		$result = wp_remote_request( $url );
		$result_body = json_decode( $result['body'], true );
		
		if( $result['response']['code'] != '200' )
			return false;
		
		if( $service == 'google_image' ) {
			$loop_results = $result_body['responseData']['results'];
			$url_path = 'unescapedUrl';
		} elseif( $service == 'flickr' ) {
			$loop_results = $result_body['photos']['photo'];
			$url_path = 'id';
		} elseif( $service == 'pixabay' ) {
			$loop_results = $result_body['hits'];
			$url_path = 'webformatURL';
		} else {
			return false;
		}
		if( !empty( $loop_results ) ) {
			$loop_count = 0;
			$numUrl = count( $loop_results );
			foreach( $loop_results as $fetch_result ) {
				
				$url_result = $fetch_result[$url_path];
				
				// FLICKR : Additional remote request to get image url
				if( $service == 'flickr' ) {
					$api_key = '63d9c292b9e2dfacd3a73908779d6d6f';
					$url = 'https://api.flickr.com/services/rest/?method=flickr.photos.getSizes&api_key=' . $api_key . '&photo_id=' . $url_result . '&format=json&nojsoncallback=1';
					$result_img_flickr = wp_remote_request( $url );
					$result_img_body_flickr = json_decode( $result_img_flickr['body'], true );
					$result = end( $result_img_body_flickr['sizes']['size'] );
					$url_result = $result['source'];
				}
				
				if( empty( $url_result ) )
					continue;
				
				$file_media = @wp_remote_request( $url_result );
				if( isset( $file_media->errors ) || $file_media['response']['code'] != 200 || strpos( $file_media['headers']['content-type'], 'text/html' ) !== false ) {
					if( ++$loop_count === $numUrl )
						return false;
					else
						continue;
				} else {
					break;
				}
			}
		} else {
			return false;
		}
		
		return array( $url_result, $file_media );
	}
	
	private function MPT_Get_Parameters( $options, $search ) {
		
		/* GOOGLE IMAGE PARAMETERS */
		if( $options['api_chosen'] == 'google_image' ) {
			
			$country =( !empty( $options['search_country']) )? $options['search_country'] : 'en' ;
			$img_color =( !empty( $options['img_color']) )? $options['img_color'] : '' ;
			$filetype =( !empty( $options['filetype']) )? $options['filetype'] : '' ;
			$imgsz =( !empty( $options['imgsz']) )? $options['imgsz'] : '' ;
			$imgtype =( !empty( $options['imgtype']) )? $options['imgtype'] : '' ;
			$safe =( !empty( $options['safe']) )? $options['safe'] : 'moderate' ;
			
			if( isset( $options['rights'] ) && !empty( $options['rights'] ) ) {
				$rights = '(';
				$last_right = array_keys( $options['rights'] );
				$last_right = end( $last_right );
				foreach( $options['rights'] as $rights_into_searching ) {
					$rights .= $rights_into_searching;
					if ( $rights_into_searching != $last_right ) {
						$rights .= '|';
					}
				}
				$rights .= ')';
			} else {
				$rights = '';
			}
			
			$array_parameters = array(
				'url'		=> 'http://ajax.googleapis.com/ajax/services/search/images',
				'imgsz'		=> $imgsz,
				'as_rights'	=> $rights,
				'imgtype'	=> $imgtype,
				'imgcolor'	=> $img_color,
				'hl'		=> $country,
				'filetype'	=> $filetype,
				'safe'		=> $safe,
				'rsz'		=> '3',
				'v'			=> '1.0',
				'q'			=> urlencode( $search ),
				'userip'	=> $_SERVER['SERVER_ADDR']
			);
			
		} 
		/* FLICKR PARAMETERS */
		elseif( $options['api_chosen'] == 'flickr' ) {
			
			$api_key = '63d9c292b9e2dfacd3a73908779d6d6f';
			$imgtype =( !empty( $options['flickr']['imgtype']) )? $options['flickr']['imgtype'] : '7' ;
			if( isset( $options['flickr']['rights'] ) && !empty( $options['flickr']['rights'] ) ) {
				$rights = '';
				$last_right = array_keys( $options['flickr']['rights'] );
				$last_right = end( $last_right );
				foreach( $options['flickr']['rights'] as $rights_into_searching ) {
					$rights .= $rights_into_searching;
					if ( $rights_into_searching != $last_right ) {
						$rights .= ',';
					}
				}
			} else {
				$rights = '0,1,2,3,4,5,6,7,8';
			}
			
			$array_parameters = array(
				'url'				=> 'https://api.flickr.com/services/rest/',
				'method'			=> 'flickr.photos.search',
				'api_key'			=> $api_key,
				'text'				=> urlencode( $search ),
				'per_page'			=> '3',
				'format'			=> 'json',
				'nojsoncallback'	=> '1',
				'privacy_filter'	=> '1',
				'license'			=> $rights,
				'sort'				=> 'relevance',
				'content_type'		=> $imgtype,
			);
			
		} 
		/* PIXABAY PARAMETERS */
		elseif( $options['api_chosen'] == 'pixabay' ) {
			
			$pixabay_username = 'magicpostthumbnail';
			$api_key = 'f7325e83e7c8a2bb9336';
			$imgtype =( !empty( $options['pixabay']['imgtype']) )? $options['pixabay']['imgtype'] : 'all' ;
			$country =( !empty( $options['pixabay']['search_country']) )? $options['pixabay']['search_country'] : 'en' ;
			$orientation =( !empty( $options['pixabay']['orientation']) )? $options['pixabay']['orientation'] : 'all' ;
			$safe =( !empty( $options['pixabay']['safesearch']) )? $options['pixabay']['safesearch'] : 'false' ;
			$min_width =( !empty( $options['pixabay']['min_width']) )? (int)$options['pixabay']['min_width'] : '0' ;
			$min_height =( !empty( $options['pixabay']['min_height']) )? (int)$options['pixabay']['min_height'] : '0' ;
			
			$array_parameters = array(
				'url'			=> 'https://pixabay.com/api/',
				'username'		=> $pixabay_username,
				'key'			=> $api_key,
				'lang'			=> $country,
				'q'				=> urlencode( $search ),
				'image_type'	=> $imgtype,
				'per_page'		=> '3',
				'orientation'	=> $orientation,
				'safesearch'	=> $safe,
				'min_width'		=> $min_width,
				'min_height'	=> $min_height,
			);
			
		} else {
			return false;
		}
		
		return $array_parameters;
		
	}
	
	/* Retrieve Image from Database, save it into Media Library, and attach it to the post as featured image */
	public function MPT_create_thumb( $id, $check_value_enable = 1, $check_post_type = 1 ) {
		
		require_once( dirname( ( __FILE__ ) ) . '/inc/default_values.php' );
		
		/* Action 'save_post' triggered when deleting posts. Check if post not trashed */
		if ( 'trash' == get_post_status( $id ) )
			return false;
		
		header('Content-type:text/html; charset=utf-8');

		if(  !current_user_can('upload_files') && !class_exists( 'WPeMatico' ) )
			return false;

		if(!function_exists('wp_get_current_user')) {
			include_once( ABSPATH . 'wp-includes/pluggable.php' );
		}

		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$post_type_availables = $post_type_availables['choosed_post_type'];


		if( $check_value_enable == '1' && get_post_meta( $id, '_mpt_value_key', true ) != '1' )
			return false;
		
		if( $check_post_type ) {
			if( !in_array( get_post_type($id), $post_type_availables ) || has_post_thumbnail( $id ) )
				return false;
		}
		
		$options = wp_parse_args( get_option( 'MPT_plugin_settings' ), default_options_settings( FALSE ) );
		
		/* Get the full title or a part of the title */
		$search = get_the_title( $id );
		if( isset( $options['title_selection'] ) && $options['title_selection'] == 'cut_title' && isset( $options['title_length'] ) ) {
			$length_title = (int)$options['title_length']-1;
			$search = preg_replace('/((\w+\W*){' . $length_title . '}(\w+))(.*)/', '${1}', $search );
		}
		
		/* SET ALL PARAMETERS */
		$array_parameters = $this->MPT_Get_Parameters( $options, $search );
		$api_url = $array_parameters['url'];
		unset( $array_parameters['url'] );
		
		/* GET THE IMAGE URL */
		list( $url_results, $file_media) = $this->MPT_Generate( 
			$options['api_chosen'], 
			$api_url,
			$array_parameters
		);
		
		if( !isset( $url_results ) || !isset( $file_media ) )
			return false;
		
		
		$path_parts = pathinfo($url_results);
		$filename = $path_parts['basename']; 
		$wp_upload_dir = wp_upload_dir();
		
		/* Get the good file extension */
		$filetype = array( 'image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/vnd.microsoft.icon', 'image/tiff', 'image/svg+xml', 'image/svg+xml' );
		$extensions = array( 'png', 'jpg', 'gif', 'bmp', 'ico', 'tif', 'svg', 'svgz' );
		if( isset( $file_media['headers']['content-type'] ) ) {
			$imgextension = str_replace( $filetype, $extensions, $file_media['headers']['content-type'], $count_extension );
			/* Default type if not found : jpg */
			if( (int)$count_extension == 0 )
				$imgextension =  'jpg';
		} else {
			$imgextension = $path_parts['extension'];
		}
		
		/* Image filename : title.extension */
		$search =  str_replace( '%', '', sanitize_title( $search ) ); // Remove % for non-latin characters
		$filename = wp_unique_filename( $wp_upload_dir['path'], $search . '.' . $imgextension );
		$folder = $wp_upload_dir['path'] .'/'. $filename;
		
		if( $file_media['response']['code'] != '200' || empty( $file_media['body'] ) )
			return false;
		
		if ( $file_media['body'] ) {
			
			/* Upload the file to wordpress directory */
			$file_upload = file_put_contents( $folder, $file_media['body'] );
			
			if( $file_upload ) {
				$wp_filetype = wp_check_filetype( basename( $filename ), null );
				
				$wp_upload_dir = wp_upload_dir();
				$attachment = array(
					'guid' => $wp_upload_dir['url'] .'/'. urlencode( $filename ), 
					'post_mime_type' => $wp_filetype['type'],
					'post_title' => $search,
					'post_content' => '',
					'post_status' => 'inherit'
				);
				$attach_id = wp_insert_attachment( $attachment, $wp_upload_dir['path'] .'/'. urlencode( $filename ) );
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $wp_upload_dir['path'] .'/'.  urlencode( $filename ) );
				$var =  wp_update_attachment_metadata( $attach_id, $attach_data );
				
				set_post_thumbnail( $id, $attach_id );
				return 1;
			}
		}
	}
	
	function MPT_menu() {
		add_options_page( 'Magic Post Thumbnail Options', 'Magic Post Thumbnail', 'manage_options', 'mpt', array( &$this, 'MPT_options' ) );
		add_action('admin_head', array( &$this, 'MPT_admin_register_head') );
		require_once( dirname( ( __FILE__ ) ) . '/inc/default_values.php' );
		register_setting('MPT-plugin-settings', 'MPT_plugin_settings');
		
		/* Generate options on Custom post type */
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		
		if( empty( $post_type_availables['choosed_post_type'] ) ) {
			return false;
		} else {
			foreach ( $post_type_availables['choosed_post_type'] as $screen ) {
				add_filter( 'bulk_actions-edit-'. $screen, array( &$this, 'MPT_add_bulk_actions' ) );
			}
		}
	}
	
	
	function MPT_admin_register_head() {
		
		if ( !empty( $_POST['mpt'] ) || !empty( $_REQUEST['ids'] ) ) {
			
			$ids = esc_attr( $_GET['ids'] );
			$ids = array_map( 'intval', explode( ',', trim( $ids, ',' ) ) );
			$count = count( $ids );
			$ids = json_encode( $ids );
?>
			<script type="text/javascript">
				var url_generation = '<?php echo plugins_url( 'inc/generate.php', __FILE__ ); ?>';
				sendposts( <?php echo $ids; ?>, 1, <?php echo $count; ?>, url_generation );
			</script>	
<?php
		}
	}
	
	/* Display MPT Options */
	public function MPT_options() {
	
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		require_once( dirname( ( __FILE__ ) ) . '/inc/admin/main.php' );
		
	}
	
	/* Set Default value when activated and never configured */
	public function MPT_default_values() {
		$options = get_option( 'MPT_plugin_settings' );
		/* Options Never set */
		if( !$options ) {
			require_once( dirname( ( __FILE__ ) ) . '/inc/default_values.php' );
			$options_default = default_options_settings( TRUE );
			update_option('MPT_plugin_settings', $options_default );
		}
	}
	
	/* Add Settings link to plugins */
	function MPT_add_settings_link( $links, $file ) {
		static $this_plugin;
		if ( !$this_plugin )
			$this_plugin = plugin_basename(__FILE__);
		 
		if ( $file == $this_plugin ){
			$settings_link = '<a href="options-general.php?page=mpt">'.__("Settings", "mpt").'</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}
	
	
	
	/* Box on posts edit screens */
	public function MPT_add_custom_box() {
		
		$id = get_the_ID();
		
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$screens = $post_type_availables['choosed_post_type'];
		
		if( empty( $screens ) ) {
			return false;
		}
		
		foreach ($screens as $screen) {
			add_meta_box(
				'myplugin_sectionid',
				'Magic Post Thumbnail',
				array( &$this, 'MPT_inner_custom_box' ),
				$screen,
				'side'
			);
		}
	}

	/* Box MPT choice for posts */
	function MPT_inner_custom_box( $post ) {
		
		wp_nonce_field( plugin_basename( __FILE__ ), 'mpt_noncename' );
		
		$value = get_post_meta( $post->ID, '_mpt_value_key', true );
		$value = ( $value != '0' )? 'checked="checked"' : '' ;
		echo '<label class="selectmpt"><input value="1" type="checkbox" name="mpt_check" '.esc_attr($value).'></label> ';
		_e( 'Plugin enabled for this post', 'mpt' );
	}

	/* Save enable/disable choice for a saved post */
	public function MPT_save_postdata( $post_id ) {

		if ( 'page' == get_post_type( $post_id ) ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		}

		if ( ! isset( $_POST['mpt_noncename'] ) || ! wp_verify_nonce( $_POST['mpt_noncename'], plugin_basename( __FILE__ ) ) )
			return;
		
		$post_ID = $_POST['post_ID'];
		
		if( !isset( $_POST['mpt_check'] ) || sanitize_text_field( $_POST['mpt_check'] ) != 1 )
			$mpt_enabled = 0;
		else
			$mpt_enabled = 1;
		
		update_post_meta($post_ID, '_mpt_value_key', $mpt_enabled);
	}
	
}


/* Launch MPT only for WP backoffice */
if( is_admin() )
	$launch_MPT = new MPT_backoffice();


/* Make it compatible with wpematico plugin */
if( defined( 'DOING_CRON' ) ) {
	// Retrieve all posts ids created by wpematico cron
	add_action( 'wpematico_cron', 'MPT_collectids', 10 );
	//Add thumbnail to every new post
	add_action( 'wpematico_cron', 'MPT_thumbnewposts', 20 );

	function MPT_collectids() {
		global $collectids;
		$collectids = array();
		add_action( 'wp_insert_post', 'MPT_collectid', 10, 1 );
	}

	function MPT_collectid( $id ) {
		global $collectids;
		$collectids[] = $id;
	}

	function MPT_thumbnewposts() {
		global $collectids;
		$cron_MPT = new MPT_backoffice();
		foreach( $collectids as $id ) {
			$cron_MPT->MPT_create_thumb( $id, 0 );
		}
	}
}

?>