<?php
/*
Plugin Name: Magic Post Thumbnail
Description: Automatically add a thumbnail for your posts. Retrieve first image from the database Google Image based on post title and add it as your featured thumbnail when you publish/update it.
Version: 2.1
Author: Alexandre Gaboriau
Author URI: http://www.alexandregaboriau.fr/


Copyright 2014 Alexandre Gaboriau (contact@alexandregaboriau.fr)

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
	
		add_action( 'save_post', array( &$this, 'create_thumb' ) );
		add_action( 'admin_menu', array( &$this, 'MPT_menu' ) );
		
		register_activation_hook( __FILE__, array( &$this, 'MPT_default_values' ) );
		
		add_filter('plugin_action_links', array(&$this, 'add_settings_link'), 10, 2 );
		
		load_plugin_textdomain( 'mpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
        add_action('admin_enqueue_scripts', array( &$this, 'apt_admin_enqueues' ) ); // Plugin hook for adding CSS and JS files required for this plugin
		
		add_action( 'add_meta_boxes', array( &$this, 'MPT_add_custom_box' ) );
		add_action( 'save_post', array( &$this, 'MPT_save_postdata' ) );
		
        add_filter( 'bulk_actions-edit-post', array( &$this, 'add_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-page', array( &$this, 'add_bulk_actions' ) );
        add_action( 'admin_action_bulk_regenerate_thumbnails', array( &$this, 'mpt_bulk_action_handler' ) ); // Top drowndown
		
    }
	
	public function apt_admin_enqueues() {
		wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'jquery-ui/jquery-ui.js', __FILE__ ), array( 'jquery-ui-core' ) );
		wp_enqueue_script( 'images-genration', plugins_url( 'jquery-ui/generation.js', __FILE__ ), array( 'jquery-ui-progressbar' ) );
		wp_enqueue_style( 'style-jquery-ui', plugins_url( 'jquery-ui/jquery-ui.css', __FILE__ ) );
		wp_enqueue_style( 'style-admin-mpt', plugins_url( 'css/admin-style.css', __FILE__ ) );	
	}
    
    public function mpt_bulk_action_handler() {
        $ids = implode( ',', array_map( 'intval', $_REQUEST['post'] ) );
        wp_redirect(  'options-general.php?page=mpt&ids=' . $ids );
        exit();
    }
    
    
    public function add_bulk_actions( $actions ) {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                $('select[name^="action"] option:last-child').before('<option value="bulk_regenerate_thumbnails"><?php echo esc_attr( __( 'Generate Magic Post thumbnail', 'mpt' ) ); ?></option>');
            });
        </script>
<?php
		return $actions;
    }
	
	/* Retrieve Image from Database, save it into Media Library, and attach it to the post as featured image */
    public function create_thumb( $id, $check_value_enable = 1 ) {
		
		//error_reporting(0);
		
		if( !current_user_can('upload_files') )
			return false;
		
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$post_type_availables = $post_type_availables['choosed_post_type'];
		
        if( $check_value_enable == '1' && get_post_meta( $id, '_mpt_value_key', true ) != '1' )
			return false;
        
		if( !in_array( get_post_type($id), $post_type_availables ) || has_post_thumbnail( $id ) )
			return false;
		
		$options = get_option( 'MPT_plugin_settings' );
		$country =( !empty( $options['search_country']) )? $options['search_country'] : 'en' ;
		$search = get_the_title( $id );
		
		
		
		/* Try Curl or allow_url_fopen */
		if( function_exists('curl_version') ) {
			$curl = true;
		}
		elseif( ini_get('allow_url_fopen') ) {
			$allow_url_fopen = true;
		}
		else{
			return false;
		}
		
		
		/* Try for first 5 images */
		for( $start=0; $start<5; $start++ ) {
			$url = 'http://ajax.googleapis.com/ajax/services/search/images?start='.$start.'&hl='.$country.'&rsz=1&v=1.0&q='.urlencode( $search );
			
			if( $curl ) {
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				$data = curl_exec($curl);
				curl_close($curl);
				$result = json_decode($data, true);
			} 
			elseif( $allow_url_fopen ) {
				$res = @file_get_contents( $url );
				$result = json_decode($res, true);
			}
			else {}
			
			if( empty($result['responseData']['results']) )
				return false;
			
			if( $result['responseStatus'] == '200' && $result['responseData']['results'][0]['unescapedUrl'] ) {
				$url_results = $result['responseData']['results'][0]['unescapedUrl'];
				break;
			} else {
				continue;
			}
		}
		
		
		$path_parts = pathinfo($url_results);
		$filename = $path_parts['basename']; 
		$wp_upload_dir = wp_upload_dir();
		$filename = wp_unique_filename( $wp_upload_dir['path'], $filename );
		$folder = $wp_upload_dir['path'] .'/'. $filename;
		
		$file_media = @file_get_contents( $url_results );
		if (!$file_media)
			continue;
		
		
		$file_upload = file_put_contents( $folder, $file_media );
		
		$size = getimagesize( $wp_upload_dir['url'] .'/'. urlencode( $filename ) );
		
		if( $size[0] == false ) {
			unlink( $wp_upload_dir['path'] .'/'. urlencode( $filename ) );
			continue;
		}
		
		
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
	
	function MPT_menu() {
		add_options_page( 'Magic Post Thumbnail Options', 'Magic Post Thumbnail', 'manage_options', 'mpt', array( &$this, 'MPT_options' ) );
		add_action('admin_head', array( &$this, 'admin_register_head') );
		register_setting('MPT-plugin-settings', 'MPT_plugin_settings');
		
		/* Generate options on Custom post type */
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$screens = $post_type_availables['choosed_post_type'];
		
		if( empty( $screens ) ) {
			return false;
		}
		
		foreach ($screens as $screen) {
			add_filter( 'bulk_actions-edit-'. $screen, array( &$this, 'add_bulk_actions' ) );
		}
	}
	
	
	function admin_register_head() {
		
		if ( !empty( $_POST['mpt'] ) || !empty( $_REQUEST['ids'] ) ) {
			
			$ids = esc_attr( $_GET['ids'] );
			$ids = array_map( 'intval', explode( ',', trim( $ids, ',' ) ) );
			$count = count( $ids );
			$ids = json_encode( $ids );
?>
			<script type="text/javascript">
				var url_generation = '<?php echo plugins_url( 'generate.php', __FILE__ ); ?>';
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
?>	
		<div id="icon-upload" class="icon32"></div>
		<div class="wrap">
			<h2>Magic Post Thumbnail : <?php _e( 'Search Preferences', 'mpt' ); ?></h2>
			<?php if( ini_get('allow_url_fopen') == 1 || function_exists('curl_version') ) { ?>
			
			
				<form method="post" action="options.php">
					<?php $test = settings_fields( 'MPT-plugin-settings' ); ?>
					<?php $options = get_option( 'MPT_plugin_settings' ); ?>
					<ul class="list-MPT">
						<li>
							<label><?php _e( 'Choose your language', 'mpt' ); ?> : </label>
							<select name="MPT_plugin_settings[search_country]" >
								<?php 
									$selected = $options['search_country'];
									$country_choose = array(
										__( 'English', 'mpt' ) => "en",
										__( 'Indonesian', 'mpt' ) => "id",
										__( 'Czech', 'mpt' ) => "cs",
										__( 'German', 'mpt' ) => "de",
										__( 'Spanish', 'mpt' ) => "es",
										__( 'French', 'mpt' ) => "fr",
										__( 'Italian', 'mpt' ) => "it",
										__( 'Dutch', 'mpt' ) => "nl",
										__( 'Norwegian', 'mpt' ) => "no",
										__( 'Hungarian', 'mpt' ) => "hu",
										__( 'Russian', 'mpt' ) => "ru",
										__( 'Polish', 'mpt' ) => "pl",
										__( 'Portuguese', 'mpt' ) => "pt",
										__( 'Romanian', 'mpt' ) => "ro",
										__( 'Finnish', 'mpt' ) => "fi",
										__( 'Swedish', 'mpt' ) => "sv",
										__( 'Turkish', 'mpt' ) => "tr",
										__( 'Japanese', 'mpt' ) => "ja",
										__( 'Korean', 'mpt' ) => "ko",
										__( 'Chinese', 'mpt' ) => "zh"
									);
									
									foreach( $country_choose as $name_country => $code_country ) {
										$choose=($selected == $code_country)?'selected="selected"': '';
										echo '<option '. $choose .' value="'. $code_country .'">'. $name_country .'</option>';
									}
								?>
							</select>
						</li>
						<li>&nbsp;</li>
						<li>
							<p class="legend_post_type"><?php _e( 'Relevant post type', 'mpt' ); ?> :</p>
						
							
							<?php
								$post_types_default = get_post_types( '', 'objects' ); 
								unset( $post_types_default['attachment'], $post_types_default['revision'], $post_types_default['nav_menu_item'] );
								
								foreach ($post_types_default  as $post_type ) {
									if( post_type_supports( $post_type->name, 'thumbnail' ) == 'true' ) {
										$checked= ( isset( $options['choosed_post_type'][$post_type->name ] ) )? 'checked="checked""' : '';
										echo '
											<label class="label_post_type" for="default_pingback_flag">
												<input '. $checked .' name="MPT_plugin_settings[choosed_post_type]['. $post_type->name .']" type="checkbox" value="'. $post_type->name .'"> '. $post_type->labels->name .'
											</label><br />
										';
									}
								}
							?>
						</li>
						<li>&nbsp;</li>
						<li><input type="submit" name="Save" value="<?php _e( 'Save Options', 'mpt' ); ?>" class="button-primary" id="submitbutton" /></li>
					</ul>
				</form>
				
				
				<?php
    				if ( ! empty( $_POST['mpt'] ) || ! empty( $_REQUEST['ids'] ) ) {
                        // Capability check
				?>	
					  <div id="ids" style="display:none;"><?php echo $_REQUEST['ids']; ?></div>
					  <div id="hide-before-import" style="display:none">
						  <div id="progressbar"></div>
						  <div id="results" ></div>
					  </div>
              <?php
                    }
			} else { ?>
				<div class="error fade">
					<p>
						<strong><?php _e( 'Sorry, your server require allow_url_fopen ON, please update your server before using this plugin', 'mpt' ); ?></strong>
					</p>
				</div>
		<?php } ?>
		</div>
<?php
	}
	
	/* Set Default value when activated and never configured */
	public function MPT_default_values() {
		$options = get_option( 'MPT_plugin_settings' );
		/* Options Never set */
		if( !$options ) {
			
			/* Default set */
			$options_default['search_country'] = 'en';
			
			/* Default all post_type activated */
			$post_types_default = get_post_types( '', 'objects');
			unset( $post_types_default['attachment'], $post_types_default['revision'], $post_types_default['nav_menu_item'] );
			foreach ($post_types_default  as $post_type ) {
				$options_default['choosed_post_type'][$post_type->name] = $post_type->name;
			}
			
			update_option('MPT_plugin_settings', $options_default );
			
		}
	}
	
	/* Add Settings link to plugins */
	function add_settings_link( $links, $file ) {
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

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		}

		if ( ! isset( $_POST['mpt_noncename'] ) || ! wp_verify_nonce( $_POST['mpt_noncename'], plugin_basename( __FILE__ ) ) )
			return;
		
		$post_ID = $_POST['post_ID'];
		$mpt_enabled = sanitize_text_field( $_POST['mpt_check'] );
		if( $mpt_enabled != 1 )
		$mpt_enabled = 0;

		add_post_meta($post_ID, '_mpt_value_key', $mpt_enabled, true) or
		update_post_meta($post_ID, '_mpt_value_key', $mpt_enabled);
	}
	
	
	
}

if( is_admin() )
	$launch_MPT = new MPT_backoffice();

?>