<?php
	require_once('../../../wp-blog-header.php');
	
	// For servers that block ajax calls
	header('HTTP/1.1 200 OK');
	
	$post_ids = array_filter( $_POST['ids'], 'ctype_digit' );
	$count = count( $post_ids );

	foreach ( $post_ids as $key => $val )
		$ids[ $key+1 ] = $val;

	$a = (int)$_POST['a']; 
	$id = $ids[$a];

	$launch_MPT = new MPT_backoffice();

	if( has_post_thumbnail( $id ) ) {
		$msg = __( 'Thumbnail\'s image of ', 'mpt' ).'<a href=\"'.get_edit_post_link( $id ).'#postimagediv\" target=\"_blank\" >'.get_the_title( $id ).'</a> '.__( ' already exist', 'mpt' );
	} elseif( !has_post_thumbnail( $id ) && $id != 0 ) {
		$MPT_return = $launch_MPT->create_thumb( $id, '0' );
		if( $MPT_return == null )
			$msg = __( 'No image on Google for ', 'mpt' ).'<a href=\"'.get_edit_post_link( $id ).'#postimagediv\" target=\"_blank\" > '.get_the_title( $id ).'</a>';
		else
			$msg = __( 'Successful image creation for ', 'mpt' ).'<a href=\"'.get_edit_post_link( $id ).'#postimagediv\" target=\"_blank\" > '.get_the_title( $id ).'</a>';
	} else {
		$msg = __( 'Error while creating image', 'mpt' );
	}
	$percent = ( 100*$a )/$count;
?>

<script type="text/javascript">
	jQuery(function() {
		  jQuery("#results").append("<?php echo $msg.'<br/>'; ?>");
		  var percent = <?php echo $percent; ?>;
		  jQuery( "#progressbar" ).progressbar({
			  value: percent
		  });
		  if( percent == 100 ) {
			jQuery("#results").append("<br/><?php _e( 'Successful generation', 'mpt' ); ?> !!");
		  }
		 
	});
</script>