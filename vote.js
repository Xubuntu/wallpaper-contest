jQuery( function( ) {
	jQuery( '.wallpaper_contest_vote .vote a' ).click( function( e ) {
		var data = {
			'action': 'wallpaper_contest_vote',
			'security': wallpaper_contest.ajaxnonce,
			'user': wallpaper_contest.user,
			'id': jQuery( this ).closest( '.item' ).attr( 'value' ),
			'value': jQuery( this ).attr( 'value' ),
		};
		current = jQuery( this );
		jQuery.post( wallpaper_contest.ajaxurl, data, function( response ) {
			if( response == 1 ) {
				current.closest( '.item' ).attr( 'data-user-vote', current.attr( 'value' ) );
				// current.closest( '.vote' ).children( 'a' ).addClass( 'unsel' );
				// current.removeClass( 'unsel' );
			}
			console.log( response );
		} );

		e.preventDefault( );
	} );
} );
