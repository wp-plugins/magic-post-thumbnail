<?php
if ( ! function_exists( 'add_filter' ) ) {		
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
		exit();
}
?>
<div class="wrap">
	
	<h2 >Magic Post Thumbnail : <?php _e( 'Search Preferences', 'mpt' ); ?></h2>
	
	<?php
		if ( ( ! empty( $_POST['mpt'] ) || ! empty( $_REQUEST['ids'] ) ) && ( empty( $_REQUEST['settings-updated'] ) || $_REQUEST['settings-updated'] != 'true' ) ) { ?>
				<div id="ids" style="display:none;"><?php echo $_REQUEST['ids']; ?></div>
				<div id="hide-before-import" style="display:none">
					<div id="progressbar"></div>
					<div id="results" ></div>
				</div>
	<?php } ?>
	
	<form method="post" action="options.php" >

		<?php settings_fields( 'MPT-plugin-settings' ); ?>
		<?php
			$options = wp_parse_args( get_option( 'MPT_plugin_settings' ), default_options_settings( FALSE ) );
		?>
		
		<table id="general-options" class="form-table">
			<tbody>
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
									$checked= ( isset( $options['choosed_post_type'][$post_type->name ] ) )? 'checked' : '';
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
						<label for="hseparator"><?php _e( 'Title', 'mpt' ); ?></label>
					</th>
					<td class="chosen_title">
						<p class="description">
							<?php _e( 'Search picture according title', 'mpt' ); ?>
						</p>
						<label><input value="full_title" name="MPT_plugin_settings[title_selection] " type="radio" <?php echo( !empty( $options['title_selection']) && $options['title_selection'] == 'full_title' )? 'checked': ''; ?> > <?php _e( 'Full title', 'mpt' ); ?></label><br/>
						<label><input value="cut_title" name="MPT_plugin_settings[title_selection] " type="radio" <?php echo( !empty( $options['title_selection']) && $options['title_selection'] == 'cut_title' )? 'checked': ''; ?>> <?php _e( 'Part of the title', 'mpt' ); ?> : </label>
						<input type="number" name="MPT_plugin_settings[title_length]" min="1" value="<?php echo( isset( $options['title_length'] ) && !empty( $options['title_length']) )? (int)$options['title_length']: '3'; ?>" <?php echo( !empty( $options['title_selection']) && $options['title_selection'] == 'cut_title' )? '': 'disabled'; ?>> <i><?php _e( 'first words of the title', 'mpt' ); ?></i>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row">
						<label for="hseparator"><?php _e( 'Image bank', 'mpt' ); ?></label>
					</th>
					<td class="chosen_api">
						<p class="description">
							<?php _e( 'Choose which database you want to search for pictures', 'mpt' ); ?>
						</p>
						<?php 
							$list_api = array(
								__( 'Google Image', 'mpt' ) => 'google_image',
								__( 'Flickr', 'mpt' ) => 'flickr',
								__( 'Pixabay', 'mpt' ) => 'pixabay',
							);
							
							foreach ( $list_api  as $api => $api_code ) {
								$checked= ( isset( $options['api_chosen'] ) && !empty( $options['api_chosen'] ) && $api_code == $options['api_chosen'] )? 'checked' : '';
								echo '<label><input type="radio" '. $checked .' value="'. $api_code .'" name="MPT_plugin_settings[api_chosen] "> '. $api .'</option></label><br/>';
							}
						?>
					</td>
				</tr>
			</tbody>
		</table>
		
		<h2 class="nav-tab-wrapper">
			<?php
				foreach ( $list_api  as $api => $api_code ) {
					if( isset( $options['api_chosen'] ) && !empty( $options['api_chosen'] ) && $api_code == $options['api_chosen'] ) {
						echo '<span href="#' . $api_code . '" class="nav-tab nav-tab-active">' . $api . '</span>';
					} else {
						echo '<span href="#' . $api_code . '" class="nav-tab" style="opacity: 0.4;" disabled="disabled">' . $api . '</span>';
					}
				}
			?>
		</h2>
		
		<?php
			foreach ( $list_api  as $api => $api_code ) {
				$checked= ( isset( $options['api_chosen'] ) && !empty( $options['api_chosen'] ) && $api_code == $options['api_chosen'] )? '' : 'style="display: none;"';
				echo '<table id="' . $api_code . '" class="form-table" ' . $checked . '>';
				echo '<tbody>';
				include_once( $api_code.'.php');
				echo '</tbody>';
				echo '</table>';
			}
		?>
		
		<?php submit_button(); ?>

	</form>
</div>
<div class="clear"></div>