<?php
/*
Plugin Name: Magic Post Thumbnail
Plugin URI: http://wordpress.org/plugins/magic-post-thumbnail/
Description: Automatically add a thumbnail for your posts. Retrieve first image from the database Google Image based on post title and add it as your featured thumbnail when you publish/update it.
Version: 2.2
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
		wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'jquery-ui/jquery-ui.js', __FILE__ ), array( 'jquery-ui-core' ) );
		wp_enqueue_script( 'images-genration', plugins_url( 'jquery-ui/generation.js', __FILE__ ), array( 'jquery-ui-progressbar' ) );
		wp_enqueue_style( 'style-jquery-ui', plugins_url( 'jquery-ui/jquery-ui.css', __FILE__ ) );
		wp_enqueue_style( 'style-admin-mpt', plugins_url( 'css/admin-style.css', __FILE__ ) );	
	}
    
    public function MPT_bulk_action_handler() {
        $ids = implode( ',', array_map( 'intval', $_REQUEST['post'] ) );
        wp_redirect(  'options-general.php?page=mpt&ids=' . $ids . '#hide-before-import' );
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
	
	/* Retrieve Image from Database, save it into Media Library, and attach it to the post as featured image */
    public function MPT_create_thumb( $id, $check_value_enable = 1, $check_post_type = 1 ) {

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
		
		$options = get_option( 'MPT_plugin_settings' );
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
		
		$search = get_the_title( $id );
		
		/* Try for first 5 images */
		for( $start=0; $start<5; $start++ ) {
			$url = 
				'http://ajax.googleapis.com/ajax/services/search/images?start='.$start.'
				&imgsz='.$imgsz.'
				&as_rights='.$rights.'
				&imgtype='.$imgtype.'
				&imgcolor='.$img_color.'
				&hl='.$country.'
				&filetype='.$filetype.'
				&safe='.$safe.'
				&rsz=1
				&v=1.0
				&q='.urlencode( $search ).'
				&userip='.$_SERVER['SERVER_ADDR'];
			
			
			
			$url = preg_replace( "/\r|\n/", "", $url );
			$url = str_replace( '	', '', preg_replace( "/\r|\n/", "", $url ) ); 
			$result = wp_remote_request( $url );
			$result = json_decode( $result['body'], true );
			
			if( empty($result['responseData']['results']) )
				return false;
			
			if( $result['responseStatus'] == '200' && $result['responseData']['results'][0]['unescapedUrl'] ) {
				$url_results = $result['responseData']['results'][0]['unescapedUrl'];
				$file_media = @wp_remote_request( $url_results );
				if( isset( $file_media->errors ) )
					continue;
				else
					break;
			} else {
				continue;
			}
		}
		
		$path_parts = pathinfo($url_results);
		$filename = $path_parts['basename']; 
		$wp_upload_dir = wp_upload_dir();
		
		/* Get the good file extension */
		$filetype = array( 'image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/vnd.microsoft.icon', 'image/tiff', 'image/svg+xml', 'image/svg+xml' );
		$extensions = array( 'png', 'jpg', 'gif', 'bmp', 'ico', 'tif', 'svg', 'svgz' );
		$imgextension = str_replace( $filetype, $extensions, $file_media['headers']['content-type'] );
		
		/* Image filename : title.extension */
		$filename = wp_unique_filename( $wp_upload_dir['path'], sanitize_title($search) . '.' . $imgextension );
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
		register_setting('MPT-plugin-settings', 'MPT_plugin_settings');
		
		/* Generate options on Custom post type */
		$post_type_availables = get_option( 'MPT_plugin_settings' );
		$screens = $post_type_availables['choosed_post_type'];
		
		if( empty( $screens ) ) {
			return false;
		}
		
		foreach ($screens as $screen) {
			add_filter( 'bulk_actions-edit-'. $screen, array( &$this, 'MPT_add_bulk_actions' ) );
		}
	}
	
	
	function MPT_admin_register_head() {
		
		if ( !empty( $_POST['mpt'] ) || !empty( $_REQUEST['ids'] ) ) {
			
			$ids = esc_attr( $_GET['ids'] );
			$ids = array_map( 'intval', explode( ',', trim( $ids, ',' ) ) );
			$count = count( $ids );
			$ids = json_encode( $ids );
			define( 'MPT_GENERATE', 1 );
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
			
				<form method="post" action="options.php">

					<?php settings_fields( 'MPT-plugin-settings' ); ?>
					<?php $options = get_option( 'MPT_plugin_settings' ); ?>


                    <table class="form-table">
                        <tbody>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Choose the language', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[search_country]" >
                                        <?php
                                            $selected = $options['search_country'];
                                            $country_choose = array(
                                                __( 'Afrikaans', 'mpt' ) => "af",
                                                __( 'Albanian', 'mpt' ) => "sq",
                                                __( 'Amharic', 'mpt' ) => "sm",
                                                __( 'Arabic', 'mpt' ) => "ar",
                                                __( 'Azerbaijani', 'mpt' ) => "az",
                                                __( 'Basque', 'mpt' ) => "eu",
                                                __( 'Belarusian', 'mpt' ) => "be",
                                                __( 'Bengali', 'mpt' ) => "bn",
                                                __( 'Bihari', 'mpt' ) => "bh",
                                                __( 'Bosnian', 'mpt' ) => "bs",
                                                __( 'Bulgarian', 'mpt' ) => "bg",
                                                __( 'Catalan', 'mpt' ) => "ca",
                                                __( 'Chinese (Simplified)', 'mpt' ) => "zh-CN",
                                                __( 'Chinese (Traditional)', 'mpt' ) => "zh-TW",
                                                __( 'Croatian', 'mpt' ) => "hr",
                                                __( 'Czech', 'mpt' ) => "cs",
                                                __( 'Danish', 'mpt' ) => "da",
                                                __( 'Dutch', 'mpt' ) => "nl",
                                                __( 'English', 'mpt' ) => "en",
                                                __( 'Esperanto', 'mpt' ) => "eo",
                                                __( 'Estonian', 'mpt' ) => "et",
                                                __( 'Faroese', 'mpt' ) => "fo",
                                                __( 'Finnish', 'mpt' ) => "fi",
                                                __( 'French', 'mpt' ) => "fr",
                                                __( 'Frisian', 'mpt' ) => "fy",
                                                __( 'Galician', 'mpt' ) => "gl",
                                                __( 'Georgian', 'mpt' ) => "ka",
                                                __( 'German', 'mpt' ) => "de",
                                                __( 'Greek', 'mpt' ) => "el",
                                                __( 'Gujarati', 'mpt' ) => "gu",
                                                __( 'Hebrew', 'mpt' ) => "iw",
                                                __( 'Hindi', 'mpt' ) => "hi",
                                                __( 'Hungarian', 'mpt' ) => "hu",
                                                __( 'Icelandic', 'mpt' ) => "is",
                                                __( 'Indonesian', 'mpt' ) => "id",
                                                __( 'Interlingua', 'mpt' ) => "ia",
                                                __( 'Irish', 'mpt' ) => "ga",
                                                __( 'Italian', 'mpt' ) => "it",
                                                __( 'Japanese', 'mpt' ) => "ja",
                                                __( 'Javanese', 'mpt' ) => "jw",
                                                __( 'Kannada', 'mpt' ) => "kn",
                                                __( 'Korean', 'mpt' ) => "ko",
                                                __( 'Latin', 'mpt' ) => "la",
                                                __( 'Latvian', 'mpt' ) => "lv",
                                                __( 'Lithuanian', 'mpt' ) => "lt",
                                                __( 'Macedonian', 'mpt' ) => "mk",
                                                __( 'Malay', 'mpt' ) => "ms",
                                                __( 'Malayam', 'mpt' ) => "ml",
                                                __( 'Maltese', 'mpt' ) => "mt",
                                                __( 'Marathi', 'mpt' ) => "mr",
                                                __( 'Nepali', 'mpt' ) => "ne",
                                                __( 'Norwegian', 'mpt' ) => "no",
                                                __( 'Norwegian (Nynorsk)', 'mpt' ) => "nn",
                                                __( 'Occitan', 'mpt' ) => "oc",
                                                __( 'Persian', 'mpt' ) => "fa",
                                                __( 'Polish', 'mpt' ) => "pl",
                                                __( 'Portuguese (Brazil)', 'mpt' ) => "pt-BR",
                                                __( 'Portuguese (Portugal)', 'mpt' ) => "pt-PT",
                                                __( 'Punjabi', 'mpt' ) => "pa",
                                                __( 'Romanian', 'mpt' ) => "ro",
                                                __( 'Russian', 'mpt' ) => "ru",
                                                __( 'Scots Gaelic', 'mpt' ) => "gd",
                                                __( 'Serbian', 'mpt' ) => "sr",
                                                __( 'Sinhalese', 'mpt' ) => "si",
                                                __( 'Slovak', 'mpt' ) => "sk",
                                                __( 'Slovenian', 'mpt' ) => "sl",
                                                __( 'Spanish', 'mpt' ) => "es",
                                                __( 'Sudanese', 'mpt' ) => "su",
                                                __( 'Swahili', 'mpt' ) => "sw",
                                                __( 'Swedish', 'mpt' ) => "sv",
                                                __( 'Tagalog', 'mpt' ) => "tl",
                                                __( 'Tamil', 'mpt' ) => "ta",
                                                __( 'Telugu', 'mpt' ) => "te",
                                                __( 'Thai', 'mpt' ) => "th",
                                                __( 'Tigrinya', 'mpt' ) => "ti",
                                                __( 'Turkish', 'mpt' ) => "tr",
                                                __( 'Ukrainian', 'mpt' ) => "uk",
                                                __( 'Urdu', 'mpt' ) => "ur",
                                                __( 'Uzbek', 'mpt' ) => "uz",
                                                __( 'Vietnamese', 'mpt' ) => "vi",
                                                __( 'Welsh', 'mpt' ) => "cy",
                                                __( 'Xhosa', 'mpt' ) => "xh",
                                                __( 'Zulu', 'mpt' ) => "zu",
                                            );
                                            ksort( $country_choose );

                                            foreach( $country_choose as $name_country => $code_country ) {
                                                $choose=($selected == $code_country)?'selected="selected"': '';
                                                echo '<option '. $choose .' value="'. $code_country .'">'. $name_country .'</option>';
                                            }
                                        ?>
                                    </select>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Relevant post type', 'mpt' ); ?>
                                </th>
                                <td>
                                    <?php
                                        $post_types_default = get_post_types( '', 'objects' );
                                        unset( $post_types_default['attachment'], $post_types_default['revision'], $post_types_default['nav_menu_item'] );

                                        foreach ($post_types_default  as $post_type ) {
                                            if( post_type_supports( $post_type->name, 'thumbnail' ) == 'true' ) {
                                                $checked= ( isset( $options['choosed_post_type'][$post_type->name ] ) )? 'checked="checked""' : '';
                                                echo '<label>
                                                    <input '. $checked .' name="MPT_plugin_settings[choosed_post_type]['. $post_type->name .']" type="checkbox" value="'. $post_type->name .'"> '. $post_type->labels->name .'
                                                </label><br/>';
                                            }
                                        }
                                    ?>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Specified color predominantly', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[img_color]" >
                                        <?php
                                        $selected = $options['img_color'];
										
                                        $img_color = array(
                                            __( '-- Default --', 'mpt' ) => '',
                                            __( 'Black', 'mpt' ) => "black",
                                            __( 'Blue', 'mpt' ) => "blue",
                                            __( 'Brown', 'mpt' ) => "brown",
                                            __( 'Gray', 'mpt' ) => "gray",
                                            __( 'Green', 'mpt' ) => "green",
                                            __( 'Orange', 'mpt' ) => "orange",
                                            __( 'Pink', 'mpt' ) => "pink",
                                            __( 'Purple', 'mpt' ) => "purple",
                                            __( 'Red', 'mpt' ) => "red",
                                            __( 'Teal', 'mpt' ) => "teal",
                                            __( 'White', 'mpt' ) => "white",
                                            __( 'Yellow', 'mpt' ) => "yellow",
                                        );
                                        ksort( $img_color );

                                        foreach( $img_color as $name_color => $code_color ) {
                                            $choose=($selected == $code_color)?'selected="selected"': '';
                                            echo '<option '. $choose .' value="'. $code_color .'">'. $name_color .'</option>';
                                        }
                                        ?>
                                    </select>
                                    <br/>
                                    <p class="description">
                                        <i><?php _e( 'Experimental', 'mpt' ); ?></i> -
                                        <?php _e( 'Restricts results to images that contain a specified color predominantly', 'mpt' ); ?>
                                    </p>
                                </td>
                            </tr>
							
							<tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Filetype', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[filetype]" >
                                        <?php
                                        $selected = $options['filetype'];
										
                                        $filetype = array(
                                            __( '-- Default --', 'mpt' ) => '',
                                            __( 'jpg', 'mpt' ) => "jpg",
                                            __( 'png', 'mpt' ) => "png",
                                            __( 'gif', 'mpt' ) => "gif",
                                            __( 'bmp', 'mpt' ) => "bmp",
                                        );
                                        ksort( $filetype );

                                        foreach( $filetype as $name_filetype => $code_filetype ) {
                                            $choose=($selected == $code_filetype)?'selected="selected"': '';
                                            echo '<option '. $choose .' value="'. $code_filetype .'">'. $name_filetype .'</option>';
                                        }
                                        ?>
                                    </select>
                                    <br/>
                                    <p class="description">
                                        <?php _e( 'Restricts image search to one of the following specific file types', 'mpt' ); ?>
                                    </p>
                                </td>
                            </tr>
							
							<tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Rights', 'mpt' ); ?></label>
                                </th>
                                <td>
									<p class="description">
                                        <?php _e( 'Choose these options can reduce relevance of results, but permit to use free-to-use images.', 'mpt' ); ?>
                                    </p>
									<?php 
										$rights_array = array(
											__( 'Publicdomain - <i>restricts search results to images with the publicdomain label.</i>', 'mpt' ) => 'cc_publicdomain',
											__( 'Attribute - <i>restricts search results to images with the attribute label.', 'mpt' ) => 'cc_attribute',
											__( 'Sharealike - <i>restricts search results to images with the sharealike label.', 'mpt' ) => 'cc_sharealike',
											__( 'Noncommercial - <i>restricts search results to images with the noncomercial label.', 'mpt' ) => 'cc_noncommercial',
											__( 'Nonderived - <i>restricts search results to images with the nonderived label.</i>', 'mpt' ) => 'cc_nonderived',
										);
									
									
										foreach ( $rights_array  as $right => $right_code ) {
											$checked= ( isset( $options['rights'] ) && !empty( $options['rights'] ) && in_array( $right_code, $options['rights'] ) )? 'checked="checked""' : '';
											echo '<label>
												<input '. $checked .' name="MPT_plugin_settings[rights]['. $right_code .']" type="checkbox" value="'. $right_code .'"> '. $right .'
											</label><br/>';
                                        }
									?>
									
                                </td>
                            </tr>
							
							<tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Image size', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[imgsz]" >
                                        <?php
                                        $selected = $options['imgsz'];
										
                                        $imgsz = array(
                                            __( '-- Default --', 'mpt' ) => '',
                                            __( 'icon', 'mpt' ) => "icon",
                                            __( 'small', 'mpt' ) => "small",
                                            __( 'medium', 'mpt' ) => "medium",
                                            __( 'large', 'mpt' ) => "large",
                                            __( 'xlarge', 'mpt' ) => "xlarge",
                                            __( 'xxlarge', 'mpt' ) => "xxlarge",
                                            __( 'huge', 'mpt' ) => "huge",
                                        );

                                        foreach( $imgsz as $name_imgsz => $code_imgsz ) {
											$choose=($selected == $code_imgsz)?'selected="selected"': '';
											echo '<option '. $choose .' value="'. $code_imgsz .'">'. $name_imgsz .'</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
							
							
							<tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Image Type', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[imgtype]" >
                                        <?php
                                        $selected = $options['imgtype'];
										
                                        $imgtype = array(
                                            __( '-- Default --', 'mpt' ) => '',
                                            __( 'Face', 'mpt' ) => "face",
                                            __( 'Photo', 'mpt' ) => "photo",
                                            __( 'Clipart', 'mpt' ) => "clipart",
                                            __( 'Lineart', 'mpt' ) => "lineart",
                                        );

                                        foreach( $imgtype as $name_imgtype => $code_imgtype ) {
                                            $choose=($selected == $code_imgtype)?'selected="selected"': '';
                                            echo '<option '. $choose .' value="'. $code_imgtype .'">'. $name_imgtype .'</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
							
							<tr valign="top">
                                <th scope="row">
                                    <label for="hseparator"><?php _e( 'Safety level', 'mpt' ); ?></label>
                                </th>
                                <td>
                                    <select name="MPT_plugin_settings[safe]" >
                                        <?php
                                        $selected = $options['safe'];
										
                                        $safe = array(
											__( 'Moderate (default)', 'mpt' ) => "moderate",
                                            __( 'Active', 'mpt' ) => "activate",
                                            __( 'Off', 'mpt' ) => "off",
                                        );

                                        foreach( $safe as $name_safe => $code_safe ) {
                                            $choose=($selected == $code_safe)?'selected="selected"': '';
                                            echo '<option '. $choose .' value="'. $code_safe .'">'. $name_safe .'</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>

                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" name="Save" value="<?php _e( 'Save Options', 'mpt' ); ?>" class="button-primary" id="submitbutton" />
                    </p>

				</form>
				
				<?php if ( ! empty( $_POST['mpt'] ) || ! empty( $_REQUEST['ids'] ) ) { ?>	
					<div id="ids" style="display:none;"><?php echo $_REQUEST['ids']; ?></div>
					<div id="hide-before-import" style="display:none">
						<div id="progressbar"></div>
						<div id="results" ></div>
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
			$options_default['img_color'] = '';
			$options_default['filetype'] = '';
			$options_default['rights'] = '';
			$options_default['imgsz'] = '';
			$options_default['imgtype'] = '';
			$options_default['safe'] = 'moderate';

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