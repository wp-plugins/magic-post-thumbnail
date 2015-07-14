<?php

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
		exit();
}

if(!function_exists("default_options_settings")) {
	function default_options_settings( $never_set = FALSE ) {
		
		if( $never_set == TRUE ) {
			$post_types_default = get_post_types( '', 'objects');
			unset( $post_types_default['attachment'], $post_types_default['revision'], $post_types_default['nav_menu_item'] );
			foreach ($post_types_default  as $post_type ) {
				$default_post_types[$post_type->name] = $post_type->name;
			}
		} else {
			$default_post_types = array();
		}
		
		$default_options = array(
		
			// GENERAL
			'choosed_post_type'	=> $default_post_types,
			'title_selection'	=> 'full_title',
			'api_chosen'		=> 'google_image',
			
			
			// GOOGLE
			'search_country'	=> 'en',
			'img_color'			=> '',
			'filetype' 			=> '',
			'rights' 			=> '',
			'imgsz' 			=> '',
			'imgtype' 			=> '',
			'safe' 				=> 'moderate',
			
			// FLICKR
			'flickr' 			=> array(
				'rights' 	=> '',
				'imgtype'	=> 7,
			),
			
			// PIXABAY
			'pixabay' 			=> array(
				'imgtype' 			=> 'all',
				'search_country'	=> 'en',
				'orientation'		=> 'all',
				'min_width'			=> 0,
				'min_height'		=> 0,
				'safesearch'		=> 'false',
			)
			
		);
		
		return $default_options;
	}
}

?>