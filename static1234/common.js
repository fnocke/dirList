
/* neutralize absence of firebug */
if ((typeof console) !== 'object' || (typeof console.info) !== 'function') {
	window.console = {};
	window.console.info = window.console.log = window.console.warn = function(msg) {};
	window.console.trace = window.console.error = window.console.assert = function() {};
}

$( function() {
	/* mark js support */
	$(document.body).addClass('js');
	
	// only, if there's a fotorama slideshow =================================
	if ( $('#fotorama').length ) { // (if slideshow existing)

		var div, api = false;

		var init = function() {
			div = $('#fotorama').show().fotorama();
			api = div.data('fotorama');
		} 

		/* name differently (anything but fotorama) to only start upon button call */
		$('.slideshow-play').click( function(){
			if(!api)
				init();
			div.show();
			api.show(0);
		});

		$(window).keyup( function(e) {
			if ( e.keyCode === 27 )
			{
				$('#fotorama').hide();
				window.location.hash = '';
			}
		});

		$('li.thumb a.view').click( function( e ) {
			var t=$(this),
				thumbId=t.attr('data-thumbid');
			
			if (!thumbId)
				console.error('missing thumbId?');
			
			window.location.hash = thumbId;
			if(!api)
				init();

			div.show();
			api.show(thumbId);
			
			e.preventDefault();
			return false;
		});
	} // slideshow ============================================================
	
}); /* jQuery */


