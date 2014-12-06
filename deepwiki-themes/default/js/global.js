
jQuery(document).ready( function() {

	/** translate code language name to Prism style and highlight */
	if ( Prism ) {
		jQuery('pre > code[class]').each( function() {
			if ( jQuery(this).hasClass( 'language-shell' ) )
				jQuery(this).attr( 'class', 'language-bash' );
			if ( jQuery(this).hasClass( 'language-js' ) )
				jQuery(this).attr( 'class', 'language-javascript' );
			if ( jQuery(this).hasClass( 'language-json' ) )
				jQuery(this).attr( 'class', 'language-javascript' );
			if ( jQuery(this).hasClass( 'language-html' ) )
				jQuery(this).attr( 'class', 'language-markup' );
		} );
		/** apply Markup to code blocks where no languages defined */
		jQuery('pre > code:not([class])').each( function() {
			jQuery(this).attr( 'class', 'language-markup' );
		} );
		/** re-highlight all code blocks */
		Prism.highlightAll();
	}

} );
