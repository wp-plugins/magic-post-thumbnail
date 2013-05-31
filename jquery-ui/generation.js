function sendposts( posts, a, count, url_generation ) {
	setTimeout(function () {
		 jQuery.ajax({
				type:"POST", data: { ids : posts, a : a, count: count }, url: url_generation,
				success: function(retour) {
					jQuery("#results").append(retour);
					document.getElementById("results").scrollTop= 100000;
					
					a++;
					if ( a <= count) {
						sendposts( posts, a, count, url_generation );
					}
					
				}
		  }).responseText;
	 }, 1);
}

jQuery(function() {
	jQuery( "#progressbar" ).progressbar({
		value: 0
	});
});

jQuery(function() {
	  jQuery("#hide-before-import").css("display", "block");
	  jQuery( "#progressbar" ).progressbar({
		   value: 1
	  });
	  return false;
});