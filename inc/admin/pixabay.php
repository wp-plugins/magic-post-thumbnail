<?php
if ( ! function_exists( 'add_filter' ) ) {		
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
		exit();
}
?>
<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Image Type', 'mpt' ); ?></label>
	</th>
	<td>
		<select name="MPT_plugin_settings[pixabay][imgtype]" >
			<?php
			$selected = $options['pixabay']['imgtype'];
			
			$imgtype = array(
				__( '-- All --', 'mpt' )	=> 'all',
				__( 'Photo', 'mpt' )		=> 'photo',	
				__( 'Screenshot', 'mpt' )	=> 'illustration',
				__( 'Other', 'mpt' )		=> 'vector',
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
		<label for="hseparator"><?php _e( 'Choose the language', 'mpt' ); ?></label>
	</th>
	<td>
		<select name="MPT_plugin_settings[pixabay][search_country]" >
			<?php
				$selected = $options['pixabay']['search_country'];
				$country_choose = array(
					__( 'Czech', 'mpt' )		=> 'cs',
					__( 'Danish', 'mpt' )		=> 'da',
					__( 'German', 'mpt' )		=> 'de',
					__( 'English', 'mpt' )		=> 'en',
					__( 'Spanish', 'mpt' )		=> 'es',
					__( 'French', 'mpt' )		=> 'fr',
					__( 'Indonesian', 'mpt' )	=> 'id',
					__( 'Italian', 'mpt' )		=> 'it',
					__( 'Hungarian', 'mpt' )	=> 'hu',
					__( 'Dutch', 'mpt' )		=> 'nl',
					__( 'Norwegian', 'mpt' )	=> 'no',
					__( 'Polish', 'mpt' )		=> 'pl',
					__( 'Portuguese', 'mpt' )	=> 'pt',
					__( 'Romanian', 'mpt' )		=> 'ro',
					__( 'Slovak', 'mpt' )		=> 'sk',
					__( 'Finnish', 'mpt' )		=> 'fi',
					__( 'Swedish', 'mpt' )		=> 'sv',
					__( 'Turkish', 'mpt' )		=> 'tr',
					__( 'Vietnamese', 'mpt' )	=> 'vi',
					__( 'Thai', 'mpt' )			=> 'th',
					__( 'Bulgarian', 'mpt' )	=> 'bg',
					__( 'Russian', 'mpt' )		=> 'ru',
					__( 'Greek', 'mpt' )		=> 'el',
					__( 'Japanese', 'mpt' )		=> 'ja',
					__( 'Korean', 'mpt' )		=> 'ko',
					__( 'Chinese', 'mpt' )		=> 'zh',
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
		<label for="hseparator"><?php _e( 'Orientation', 'mpt' ); ?></label>
	</th>
	<td>
		<select name="MPT_plugin_settings[pixabay][orientation]" >
			<?php
			$selected = $options['pixabay']['orientation'];
			
			$orientation = array(
				__( '-- All --', 'mpt' )	=> 'all',
				__( 'Horizontal', 'mpt' )	=> 'horizontal',	
				__( 'Vertical', 'mpt' )		=> 'vertical',
			);

			foreach( $orientation as $name_orientation => $code_orientation ) {
				$choose=($selected == $code_orientation)?'selected="selected"': '';
				echo '<option '. $choose .' value="'. $code_orientation .'">'. $name_orientation .'</option>';
			}
			?>
		</select>
	</td>
</tr>


<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Minimum width', 'mpt' ); ?></label>
	</th>
	<td>
		<input type="number" name="MPT_plugin_settings[pixabay][min_width]" min="0" value="<?php echo( isset( $options['pixabay']['min_width'] ) && !empty( $options['pixabay']['min_width']) )? (int)$options['pixabay']['min_width']: '0'; ?>" > <i>px minimum for width</i>
	</td>
</tr>



<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Minimum height', 'mpt' ); ?></label>
	</th>
	<td>
		<input type="number" name="MPT_plugin_settings[pixabay][min_height]" min="0" value="<?php echo( isset( $options['pixabay']['min_height'] ) && !empty( $options['pixabay']['min_height']) )? (int)$options['pixabay']['min_height']: '0'; ?>" > <i>px minimum for height</i>
	</td>
</tr>



<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Safesearch', 'mpt' ); ?></label>
	</th>
	<td>
		<select name="MPT_plugin_settings[pixabay][safesearch]" >
			<?php
			$selected = $options['pixabay']['safesearch'];
			
			$safesearch = array(
				__( 'Off', 'mpt' )		=> 'false',
				__( 'Active', 'mpt' )	=> 'true',	
			);

			foreach( $safesearch as $name_safesearch => $code_safesearch ) {
				$choose=($selected == $code_safesearch)?'selected="selected"': '';
				echo '<option '. $choose .' value="'. $code_safesearch .'">'. $name_safesearch .'</option>';
			}
			?>
		</select>
	</td>
</tr>