<?php
if ( ! function_exists( 'add_filter' ) ) {		
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
		exit();
}
?>
<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Rights', 'mpt' ); ?></label>
	</th>
	<td>
		<p class="description">
			<?php _e( 'Choose which licence works for pictures. Licences chosen are cumulative.', 'mpt' ); ?><br/>
			<?php _e( 'If none of these options are chosen, every licences will be used.', 'mpt' ); ?>
		</p>
		<?php 
			$rights_array = array(
				__( 'All Rights Reserved', 'mpt' )																																	=> '0',
				__( 'Attribution-NonCommercial-ShareAlike License - <i><a href="http://creativecommons.org/licenses/by-nc-sa/2.0/" target="_blank">More detail</a></i>', 'mpt' )	=> '1',
				__( 'Attribution-NonCommercial License - <i><a href="http://creativecommons.org/licenses/by-nc/2.0/" target="_blank">More detail</a></i>', 'mpt' )					=> '2',
				__( 'Attribution-NonCommercial-NoDerivs License - <i><a href="http://creativecommons.org/licenses/by-nc-nd/2.0/" target="_blank">More detail</a></i>', 'mpt' )		=> '3',
				__( 'Attribution License - <i><a href="http://creativecommons.org/licenses/by/2.0/" target="_blank">More detail</a></i>', 'mpt' )									=> '4',
				__( 'Attribution License - <i><a href="http://creativecommons.org/licenses/by-sa/2.0/" target="_blank">More detail</a></i>', 'mpt' )								=> '5',
				__( 'Attribution-NoDerivs License - <i><a href="http://creativecommons.org/licenses/by-nd/2.0/" target="_blank">More detail</a></i>', 'mpt' )						=> '6',
				__( 'No known copyright restrictions - <i><a href="http://flickr.com/commons/usage/" target="_blank">More detail</a></i>', 'mpt' )									=> '7',
				__( 'United States Government Work - <i><a href="http://www.usa.gov/copyright.shtml" target="_blank">More detail</a></i>', 'mpt' )									=> '8',
				
			);
		
		
			foreach ( $rights_array  as $right => $right_code ) {
				$checked= ( isset( $options['flickr']['rights'] ) && !empty( $options['flickr']['rights'] ) && in_array( $right_code, $options['flickr']['rights'] ) )? 'checked="checked""' : '';
				echo '
				<label>
					<input '. $checked .' name="MPT_plugin_settings[flickr][rights]['. $right_code .']" type="checkbox" value="'. $right_code .'"> '. $right .'
				</label><br/>
				';
			}
		?>
		
	</td>
</tr>


<tr valign="top">
	<th scope="row">
		<label for="hseparator"><?php _e( 'Image Type', 'mpt' ); ?></label>
	</th>
	<td>
		<select name="MPT_plugin_settings[flickr][imgtype]" >
			<?php
			$selected = $options['flickr']['imgtype'];
			
			$imgtype = array(
				__( '-- All --', 'mpt' )				=> '7',
				__( 'Photo', 'mpt' )					=> '1',
				__( 'Screenshot', 'mpt' )				=> '2',
				__( 'Other', 'mpt' )					=> '3',
				__( 'Photo and screenshot', 'mpt' )		=> '4',
				__( 'Screenshot and "other"', 'mpt' )	=> '5',
				__( 'Photo and "other"', 'mpt' ) 		=> '6',
			);

			foreach( $imgtype as $name_imgtype => $code_imgtype ) {
				$choose=($selected == $code_imgtype)?'selected="selected"': '';
				echo '<option '. $choose .' value="'. $code_imgtype .'">'. $name_imgtype .'</option>';
			}
			?>
		</select>
	</td>
</tr>