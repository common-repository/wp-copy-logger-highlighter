;

var CPLH = {};

(function ( $ ) {

	/* Main object. Can also be seen in the DOM panel */
	CPLH = {
		init                 : function () {
			var _this = CPLH;

			/* These vars are passed by wp_localize_script in CPLH->logger_enqueue() */
			_this.wpAjax = typeof CPLH_VARS !== 'undefined' && CPLH_VARS.wp_ajax !== 'undefined' ? CPLH_VARS.wp_ajax : false;
			_this.debugOn = typeof CPLH_VARS !== 'undefined' && CPLH_VARS.debug_on !== 'undefined' ? 1 == CPLH_VARS.debug_on : false; // non-typed comparison on purpose

			_this.$body = $( 'body' );


			_this.bindEvents();

		},
		bindEvents           : function () {
			var _this = CPLH;

			// Bind the 'copy' event to our body
			_this.$body.on( 'copy', function ( e ) {
				event.preventDefault();

				var copytext =  window.getSelection() + "\n\n" + CPLH_VARS.attach_message.replace("\n\n", "\n");

				if (window.clipboardData) {
					window.clipboardData.setData('Text', copytext);
				}
				if (event.clipboardData) {
					event.clipboardData.setData('Text', copytext);
				}

				_this.currSelection = _this.getSelection();
				_this.parseWindowSelection();

				if ( _this.currSelection && _this.currNumSelections > 0 ) {
					_this.ajaxSendSelections();
				}

				if ( _this.debugOn ) {
					console.log('[CPLH] CPLH.currSelection: ', _this.currSelection);
					console.log('[CPLH] CPLH.currentSelectedTexts: ', _this.currSelectedTexts);
					console.log('[CPLH] CPLH_VARS.post_id_maybe: ', CPLH_VARS.post_id_maybe);
				}
			} );


			// Clearing selection when you click somewhere
			// MB: Not sure about this one...
			// _this.$body.on( 'click', function ( e ) {
			// 	_this.resetSelection();
			// } );

		},
		getSelection         : function () {
			var _this = CPLH;

			return window.getSelection();
		},
		parseWindowSelection : function () {
			var _this = CPLH;

			// @todo : validate that something really IS selected
			if ( _this.currSelection ) {
				_this.currNumSelections = _this.currSelection.rangeCount;

				/* No point in going on if nothing's selected */
				if ( _this.currNumSelections < 1 ) {
					_this.resetSelection();
					return;
				}

				/* The window.getSelection() method can return multiple selections (CTRL+drag-select, for instance) */
				_this.currSelectedTexts = [];
				for ( var i = 0; i < _this.currSelection.rangeCount; i++ ) {
					_this.currSelectedTexts.push( _this.currSelection.getRangeAt( i ).toString() );
				}

			} else {
				// Nothing selected. Let's reset!
				_this.resetSelection();
			}
		},
		resetSelection       : function () {
			var _this = CPLH;

			_this.currSelectedTexts = [];
			_this.currSelection = false;
		},
		ajaxSendSelections   : function () {
			var _this = CPLH;

			if ( _this.debugOn && !_this.wpAjax ) {
				console.error( '[CPLH] WP ajax endpoint not defined' );
				return;
			}

			$.ajax( {
					method   : "POST",
					url      : _this.wpAjax,
					dataType : 'JSON',
					data     : {
						action : 'cplh_save_copied_selection', // Must match 'wp_ajax_cplh_save_copied_selection' && 'wp_ajax_nopriv_cplh_save_copied_selection'
						data   : {
							texts:  _this.currSelectedTexts,
							curr_url: window.location.href,
                            post_id: CPLH_VARS.post_id_maybe
						}
					}
				} )

				.done( function ( _data ) {
					if ( _this.debugOn ) {
						console.log( '[CPLH] Ajax response after sending text: ', _data );
					}
				} )

				.always( function ( _data ) {
					if ( typeof _data === 'undefined' || typeof _data.success === 'undefined' || 1 != _data.success ) {
						if ( _this.debugOn ) {
							console.error( '[CPLH] Something went wrong trying to send the AJAX to WP', _data );
						}
					}
				} );

		}


	}


	/* Let's roll! */
	CPLH.init();

})( jQuery );