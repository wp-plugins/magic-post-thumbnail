<?php
/*
Plugin Name: Magic Post Thumbnail
Description: Automatically add a thumbnail for your posts. Retrieve first image from Google Images based on post title and add it as your featured thumbnail when you publish/update it.
Version: 1.3.1
Author: Alexandre Gaboriau
Author URI: http://www.alex.re/


Copyright 2013 Alexandre Gaboriau (contact@alexandregaboriau.fr)

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
		
        add_filter( 'bulk_actions-edit-post', array( &$this, 'add_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-page', array( &$this, 'add_bulk_actions' ) );
        add_action( 'admin_action_bulk_regenerate_thumbnails', array( &$this, 'mpt_bulk_action_handler' ) ); // Top drowndown
		
		add_action( 'add_meta_boxes', array( &$this, 'MPT_add_custom_box' ) );
		add_action( 'save_post', array( &$this, 'MPT_save_postdata' ) );
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
	
	/* Retrieve Image from Google, save it into Media Library, and attach it to the post as featured image */
    public function create_thumb( $id, $check_value_enable = 1 ) {
		
		error_reporting(0);
		
		if( !current_user_can('upload_files') )
			return false;
		
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$post_type_availables = $post_type_availables['choosed_post_type'];
		
        if( $check_value_enable == '1' && get_post_meta( $id, '_mpt_value_key', true ) != '1' )
			return false;
        
		if( !in_array( get_post_type($id), $post_type_availables ) || has_post_thumbnail( $id ) )
			return false;
		
		$search = get_the_title( $id );
		
		$search = urlencode( $search );
		$options = get_option( 'MPT_plugin_settings' );
		$safe =( !empty( $options['google_safe']) )? $options['google_safe'] : 'off' ;
		$country =( !empty( $options['search_country']) )? $options['search_country'] : 'com' ;
		$rights =( !empty( $options['rights']) )? $options['rights'] : '' ;
		$url = "http://www.google.$country&site=images&source=gp&q=$search&channel=gp1&og=gp&start=0&sa=N&tbs=$rights&safe=$safe&tbm=isch";
        
		$url = str_replace(" ", "+", $url);
		
		// Testing allow_url_fopen ON
		 if (ini_get('allow_url_fopen')) {
			$res = @file_get_contents( $url );
		} else {
			//TODO : Error message allow_url_fopen off
			return false;
		}
		
		if (!$res)
            return null;
		
		
		$str = explode( 'imgurl', $res );
		
		if ( !isset( $str[1] ) )
			return null;
		
		/* Try with the first 10 images */
		for( $a=1; $a<10; $a++ ) {
			$url3 = $str[$a];
			$URL_clean_1 = stripos($url3,'=')+strlen('&');
			$URL_clean_2 = stripos($url3,'&',$URL_clean_1+1);
			$url3 = substr( $url3, $URL_clean_1, $URL_clean_2-$URL_clean_1 );
			
			if ( $url3 != "" ) {
				$url3 = str_replace( array( '%2B' ), '-', $url3 );
				$path_parts = pathinfo($url3);
				$filename = $path_parts['basename']; 
				$wp_upload_dir = wp_upload_dir();
				$filename = wp_unique_filename( $wp_upload_dir['path'], $filename );
				$folder = $wp_upload_dir['path'] .'/'. $filename;
				
				$file_media = @file_get_contents( $url3 );
				if (!$file_media)
					continue;
				
				$file_upload = file_put_contents( $folder, $file_media );
				
				$size = getimagesize( $wp_upload_dir['url'] .'/'. _wp_relative_upload_path( $filename ) );
				
				if( $size[0] == false ) {
					unlink( $wp_upload_dir['path'] .'/'. _wp_relative_upload_path( $filename ) );
					continue;
				}
			
				if( (int)$file_upload )
					break;
			}
			
		}
		
		$wp_filetype = wp_check_filetype( basename( $filename ), null );
		
		$wp_upload_dir = wp_upload_dir();
		$attachment = array(
			'guid' => $wp_upload_dir['url'] .'/'. _wp_relative_upload_path( $filename ), 
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment( $attachment, $wp_upload_dir['url'] .'/'. _wp_relative_upload_path( $filename ) );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $wp_upload_dir['path'] .'/'. $filename );
		$var =  wp_update_attachment_metadata( $attach_id, $attach_data );
		
		set_post_thumbnail( $id, $attach_id );
		return 1;
    }
	
	function MPT_menu() {
		add_options_page( 'Magic Post Thumbnail Options', 'Magic Post Thumbnail', 'manage_options', 'mpt', array( &$this, 'MPT_options' ) );
		add_action('admin_head', array( &$this, 'admin_register_head') );
		register_setting('MPT-plugin-settings', 'MPT_plugin_settings');
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
			<h2>Magic Post Thumbnail : <?php _e( 'Google Image Search Preferences', 'mpt' ); ?></h2>
			<?php if( ini_get('allow_url_fopen') == 1 ) { ?>
				<form method="post" action="options.php">
					<?php $test = settings_fields( 'MPT-plugin-settings' ); ?>
					<?php $options = get_option( 'MPT_plugin_settings' ); ?>
					<ul class="list-MPT">
						<li>
							<label><?php _e( 'Google Safe search', 'mpt' ); ?> : </label>
							<select name="MPT_plugin_settings[google_safe]" >
								<option <?php echo($options['google_safe']=='off')? 'selected="selected"' : ''; ?> value="off"><?php _e( 'Off', 'mpt' ); ?></option>
								<option <?php echo($options['google_safe']=='on')? 'selected="selected"' : ''; ?> value="on"><?php _e( 'On', 'mpt' ); ?></option>
							</select>
							<i><?php _e( 'Safe mode protects images sexually explicit', 'mpt' ); ?></i>
						</li>
						<li>
							<label><?php _e( 'Choose your Google country\'s search', 'mpt' ); ?> : </label>
							<select name="MPT_plugin_settings[search_country]" >
								<?php 
									$selected = $options['search_country'];
									$country_choose = array(
										__( 'International', 'mpt' ) => "com/m/search?",
										__( 'Brazil', 'mpt' ) => "com.br/m/search?hl=pt-BR",
										__( 'Canada', 'mpt' ) => "ca/m/search?hl=en",
										__( 'China', 'mpt' ) => "com/m/search?hl=zh-CN",
										__( 'Egypt', 'mpt' ) => "com.eg/m/webhp?hl=ar",
										__( 'France', 'mpt' ) => "fr/m/search?hl=fr",
										__( 'Germany', 'mpt' ) => "de/m/search?hl=de",
										__( 'Italia', 'mpt' ) => "it/m/search?hl=it",
										__( 'Japan', 'mpt' ) => "co.jp/m/search?hl=ja",
										__( 'Netherlands', 'mpt' ) => "nl/m/search?hl=nl",
										__( 'Mexico', 'mpt' ) => "co.ma/m/search?hl=ar",
										__( 'Morocco', 'mpt' ) => "it/m/search?hl=it",
										__( 'Peru', 'mpt' ) => "com.pe/m/search?hl=es",
										__( 'South Africa', 'mpt' ) => "co.za/m/search?hl=en",
										__( 'Spain', 'mpt' ) => "es/m/search?hl=es",
										__( 'Swiss', 'mpt' ) => "ch/m/search?hl=de",
										__( 'UK', 'mpt' ) => "co.uk/m/search?hl=en",
										__( 'USA', 'mpt' ) => "com/m/search?hl=en"
									);
									
									foreach( $country_choose as $name_country => $code_country ) {
										$choose=($selected == $code_country)?'selected="selected"': '';
										echo '<option '. $choose .' value="'. $code_country .'">'. $name_country .'</option>';
									}
								?>
							</select>
							<i><?php _e( 'Country\'s google search', 'mpt' ); ?></i>
						</li>
						<li>
							<label><?php _e( 'Usage rights', 'mpt' ); ?> : </label>
							<select name="MPT_plugin_settings[rights]" >
								<option <?php echo( empty( $options['rights'] ) )? 'selected="selected"' : ''; ?> value=""><?php _e( 'not filtered by license', 'mpt' ); ?></option>
								<option <?php echo($options['rights']=='sur:f')? 'selected="selected"' : ''; ?> value="sur:f"><?php _e( 'free to use or share', 'mpt' ); ?></option>
								<option <?php echo($options['rights']=='sur:fc')? 'selected="selected"' : ''; ?> value="sur:fc"><?php _e( 'free to use or share, even commercially', 'mpt' ); ?></option>
								<option <?php echo($options['rights']=='sur:fm')? 'selected="selected"' : ''; ?> value="sur:fm"><?php _e( 'free to use share or modify', 'mpt' ); ?></option>
								<option <?php echo($options['rights']=='sur:fmc')? 'selected="selected"' : ''; ?> value="sur:fmc"><?php _e( 'free to use, share or modify, even commercially', 'mpt' ); ?></option>
							</select>
							<br /><i><?php _e( 'Filter only images royalty-free ... <b> Warning </b>: These filters can reduce the relevance of some results', 'mpt' ); ?></i>
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
			$options_default['google_safe'] = 'off';
			$options_default['search_country'] = 'com/m/search?';
			$options_default['rights'] = '';
			
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