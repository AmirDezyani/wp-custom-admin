/**
 * WP Custom Admin — settings page behavior.
 *
 * Tab switching (vanilla), color pickers (wp-color-picker/Iris), and the logo
 * Media Library picker (wp.media). jQuery is used only because wp-color-picker
 * requires it; it is a core-bundled dependency, so no build step is involved.
 */
( function ( $ ) {
	'use strict';

	var __ = ( window.wp && wp.i18n && wp.i18n.__ )
		? wp.i18n.__
		: function ( text ) { return text; };

	function initTabs() {
		var tabs = document.querySelectorAll( '.wpca-tab' );
		var panels = document.querySelectorAll( '.wpca-panel' );
		var actions = document.querySelector( '.wpca-actions' );

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = tab.getAttribute( 'data-target' );

				tabs.forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
				} );

				panels.forEach( function ( panel ) {
					panel.classList.toggle(
						'is-active',
						panel.getAttribute( 'data-tab' ) === target
					);
				} );

				// The save bar belongs to the settings form; hide it on the Tools tab.
				if ( actions ) {
					actions.style.display = 'tools' === target ? 'none' : '';
				}
			} );
		} );
	}

	function initColorPickers() {
		if ( ! $.fn.wpColorPicker ) {
			return;
		}

		$( '.wpca-color' ).wpColorPicker( {
			// Recolor the surrounding (already-reskinned) admin live as you pick.
			change: function ( event, ui ) {
				var cssVar = this.getAttribute( 'data-css-var' );

				if ( cssVar && ui && ui.color ) {
					document.documentElement.style.setProperty( cssVar, ui.color.toString() );
				}
			},
		} );
	}

	function initMenuSort() {
		var $body = $( '.wpca-menu-sortable' );

		if ( $body.length && $.fn.sortable ) {
			$body.sortable( {
				items: '> tr',
				handle: '.wpca-menu-handle',
				axis: 'y',
				placeholder: 'wpca-menu-placeholder',
				forcePlaceholderSize: true,
			} );
		}
	}

	function setHeaderLogo( src ) {
		var img = document.querySelector( '.wpca-header-logo' );
		var icon = document.querySelector( '.wpca-header-icon' );
		if ( img ) {
			if ( src ) {
				img.src = src;
				img.style.display = '';
			} else {
				img.style.display = 'none';
			}
		}
		if ( icon ) {
			icon.style.display = src ? 'none' : '';
		}
	}

	function initMediaPickers() {
		$( '.wpca-media' ).each( function () {
			var $wrap = $( this );
			var $input = $wrap.find( '.wpca-media-id' );
			var $preview = $wrap.find( '.wpca-media-preview' );
			var $remove = $wrap.find( '.wpca-media-remove' );
			var frame;

			$wrap.on( 'click', '.wpca-media-select', function ( event ) {
				event.preventDefault();

				if ( frame ) {
					frame.open();
					return;
				}

				frame = wp.media( {
					title: __( 'Select image', 'wp-custom-admin' ),
					button: { text: __( 'Use image', 'wp-custom-admin' ) },
					library: { type: 'image' },
					multiple: false,
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var src = attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;

					$input.val( attachment.id );
					$preview.html( $( '<img />' ).attr( 'src', src ) );
					$remove.show();

					if ( 'logo_id' === $wrap.data( 'key' ) ) {
						setHeaderLogo( src );
					}
				} );

				frame.open();
			} );

			$wrap.on( 'click', '.wpca-media-remove', function ( event ) {
				event.preventDefault();
				$input.val( '' );
				$preview.empty();
				$remove.hide();

				if ( 'logo_id' === $wrap.data( 'key' ) ) {
					setHeaderLogo( '' );
				}
			} );
		} );
	}

	$( function () {
		initTabs();
		initColorPickers();
		initMediaPickers();
		initMenuSort();
	} );
}( jQuery ) );
