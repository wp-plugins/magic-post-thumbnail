jQuery(document).ready(function() {
	
	/* TAB CHOSEN */
	jQuery("#general-options .chosen_api input").change(function(){
		var tab = '#'+jQuery(this).val();
		var link_tab = ".nav-tab-wrapper span[href="+tab+"]";
		
		jQuery(link_tab).addClass("nav-tab-active").css( "opacity", "1" ).removeAttr('disabled');
		jQuery(link_tab).siblings().removeClass("nav-tab-active").css( "opacity", "0.4" ).attr('disabled', 'disabled');
		
		jQuery("#wpbody-content .form-table").not(tab).not('#general-options').css("display", "none");
		jQuery(tab).fadeIn();
		
	});
	
	/* TITLE SELECTION */
	jQuery("#general-options .chosen_title input[type='radio']").change(function(){
		if( jQuery(this).val() == 'cut_title' ) {
			jQuery( "input[name='MPT_plugin_settings[title_length]']" ).removeAttr('disabled');
		} else {
			jQuery( "input[name='MPT_plugin_settings[title_length]']" ).attr('disabled', 'disabled');
		}
	});
});