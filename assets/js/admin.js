/**
 * WP Custom Admin — command palette (Ctrl/Cmd K).
 *
 * Vanilla JS, no build, no jQuery, no wp.i18n. Reads the localized `wpcaPalette`
 * object ({ items, i18n, trigger }) printed by CommandPaletteModule and lazily
 * builds an accessible, RTL-aware quick-switcher overlay on first open.
 *
 * SECURITY: every piece of dynamic text (labels, breadcrumbs, match highlights)
 * enters the DOM via textContent / createElement ONLY — never innerHTML. The single
 * attribute taking item data is the anchor target passed to location.assign(), which
 * the server already ran through esc_url_raw(); as defense-in-depth the consumer also
 * re-checks the protocol before navigating. There are no writes (read-only GET
 * navigation), so no nonce is involved.
 *
 * A11y: APG "combobox with listbox" pattern — the input keeps real focus while
 * selection is virtual via aria-activedescendant over role=option rows. A visible/SR
 * trigger button in the admin bar gives a discoverable affordance beyond the shortcut.
 */
( function () {
	'use strict';

	var data = window.wpcaPalette;

	// Bail quietly if the localized payload is missing or malformed.
	if ( ! data || typeof data !== 'object' || ! Array.isArray( data.items ) ) {
		return;
	}

	var ITEMS = data.items;
	var I18N = data.i18n || {};
	var TRIGGER = data.trigger || {};

	var IS_MAC = /mac|iphone|ipad|ipod/i.test(
		( navigator.userAgentData && navigator.userAgentData.platform ) ||
			navigator.platform ||
			''
	);

	// ---- module-scoped state (no globals leaked) ---------------------------
	var built = false;
	var open = false;
	var els = {};          // cached DOM references
	var rows = [];         // [{ item, score, ranges }] current filtered results
	var rowNodes = [];     // <li> for each row (parallel to rows)
	var active = -1;       // index into rows of the active option
	var returnFocus = null; // element to restore focus to on close
	var hidden = [];       // background elements we set aria-hidden on (restore symmetrically)

	var ID_BASE = 'wpca-cmdk-opt-';

	// ---- text helpers ------------------------------------------------------

	function t( key, fallback ) {
		return typeof I18N[ key ] === 'string' ? I18N[ key ] : fallback;
	}

	// Platform-correct shortcut glyph for the visible hint + trigger button.
	function triggerGlyph() {
		return IS_MAC ? ( TRIGGER.mac || '⌘K' ) : ( TRIGGER.other || 'Ctrl K' );
	}

	// Fold diacritics + Persian/Arabic variants + digit sets so queries match loosely.
	function normalize( str ) {
		var s = String( str ).toLowerCase();

		if ( s.normalize ) {
			// Decompose, then strip combining-mark ranges (Latin U+0300–036F +
			// Arabic U+0610–065F, U+0670, U+06D6–06ED).
			s = s.normalize( 'NFKD' ).replace(
				/[̀-ͯؐ-ًؚ-ٰٟۖ-ۜ۟-۪ۤۧۨ-ۭ]/g,
				''
			);
		}

		// Normalize common Arabic/Persian letter variants.
		s = s
			.replace( /[يى]/g, 'ی' ) // Arabic Yeh / Alef Maksura -> Farsi Yeh
			.replace( /ك/g, 'ک' )         // Arabic Kaf -> Keheh
			.replace( /[‌‎‏]/g, '' ); // strip ZWNJ / LRM / RLM marks

		// Fold Persian (U+06F0–9) and Arabic-Indic (U+0660–9) digits to ASCII, so a
		// query typed with Persian digits matches a label carrying ASCII digits.
		s = s.replace( /[۰-۹]/g, function ( d ) {
			return String( d.charCodeAt( 0 ) - 0x06f0 );
		} ).replace( /[٠-٩]/g, function ( d ) {
			return String( d.charCodeAt( 0 ) - 0x0660 );
		} );

		return s;
	}

	// ---- fuzzy subsequence match + ranking ---------------------------------

	/**
	 * Match query chars as an in-order subsequence of the haystack.
	 * Returns { score, ranges } (ranges = matched [start,end) pairs in `label`)
	 * or null when not all query chars are found.
	 */
	function fuzzy( queryNorm, label, haystack ) {
		if ( '' === queryNorm ) {
			return { score: 0, ranges: [] };
		}

		var hay = normalize( haystack );
		var labelNorm = normalize( label );
		var score = 0;
		var ranges = [];
		var qi = 0;
		var prevHit = -2;
		var firstHit = -1;
		var labelLen = labelNorm.length;

		for ( var hi = 0; hi < hay.length && qi < queryNorm.length; hi++ ) {
			if ( hay[ hi ] !== queryNorm[ qi ] ) {
				continue;
			}

			if ( -1 === firstHit ) {
				firstHit = hi;
			}

			// Contiguous-run bonus.
			if ( hi === prevHit + 1 ) {
				score += 8;
			}

			// Word-boundary bonus (start, or after a separator).
			var prevChar = hi > 0 ? hay[ hi - 1 ] : ' ';
			if ( 0 === hi || ' ' === prevChar || '-' === prevChar || '/' === prevChar || '_' === prevChar ) {
				score += 6;
			}

			// Record highlight range against the LABEL when it lines up (haystack
			// is "label + parent", so label-prefix indices coincide).
			if ( hi < labelLen ) {
				ranges.push( [ hi, hi + 1 ] );
			}

			prevHit = hi;
			qi++;
		}

		// Not every query char matched -> reject.
		if ( qi < queryNorm.length ) {
			return null;
		}

		// Exact / prefix / earliness / brevity signals.
		if ( labelNorm === queryNorm ) {
			score += 100;
		} else if ( 0 === labelNorm.indexOf( queryNorm ) ) {
			score += 40;
		}
		score += Math.max( 0, 20 - firstHit );   // earlier first match is better
		score += Math.max( 0, 30 - labelLen );   // shorter label is a tighter match

		return { score: score, ranges: mergeRanges( ranges ) };
	}

	function mergeRanges( ranges ) {
		if ( ranges.length < 2 ) {
			return ranges;
		}
		var out = [ ranges[ 0 ].slice() ];
		for ( var i = 1; i < ranges.length; i++ ) {
			var last = out[ out.length - 1 ];
			if ( ranges[ i ][ 0 ] <= last[ 1 ] ) {
				last[ 1 ] = Math.max( last[ 1 ], ranges[ i ][ 1 ] );
			} else {
				out.push( ranges[ i ].slice() );
			}
		}
		return out;
	}

	function filter( query ) {
		var q = normalize( query.trim() );
		var matched = [];

		for ( var i = 0; i < ITEMS.length; i++ ) {
			var item = ITEMS[ i ];
			var haystack = item.label + ' ' + ( item.parent || '' );
			var res = fuzzy( q, item.label, haystack );
			if ( null !== res ) {
				matched.push( { item: item, score: res.score, ranges: res.ranges } );
			}
		}

		// Sort by score desc, then label A-Z for a stable, non-jittery order.
		matched.sort( function ( a, b ) {
			if ( b.score !== a.score ) {
				return b.score - a.score;
			}
			return a.item.label.localeCompare( b.item.label );
		} );

		return matched;
	}

	// ---- DOM construction (once) -------------------------------------------

	function build() {
		var overlay = document.createElement( 'div' );
		overlay.className = 'wpca-cmdk-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', t( 'dialogLabel', 'Command palette' ) );

		var card = document.createElement( 'div' );
		card.className = 'wpca-cmdk-card';

		// --- input row ---
		var inputRow = document.createElement( 'div' );
		inputRow.className = 'wpca-cmdk-input-row';

		var icon = document.createElement( 'span' );
		icon.className = 'wpca-cmdk-search-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.appendChild( searchSvg() );

		var listId = 'wpca-cmdk-listbox';

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'wpca-cmdk-input';
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-label', t( 'searchLabel', 'Search' ) );
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'autocorrect', 'off' );
		input.setAttribute( 'autocapitalize', 'off' );
		input.setAttribute( 'spellcheck', 'false' );
		input.setAttribute( 'dir', 'auto' ); // Persian query renders RTL, Latin LTR.
		input.placeholder = t( 'placeholder', 'Search admin pages…' );

		var escChip = document.createElement( 'span' );
		escChip.className = 'wpca-cmdk-kbd';
		escChip.textContent = t( 'escHint', 'Esc' );

		inputRow.appendChild( icon );
		inputRow.appendChild( input );
		inputRow.appendChild( escChip );

		// --- results listbox ---
		var list = document.createElement( 'ul' );
		list.className = 'wpca-cmdk-list';
		list.id = listId;
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-label', t( 'resultsLabel', 'Results' ) );

		// --- empty state ---
		var empty = document.createElement( 'div' );
		empty.className = 'wpca-cmdk-empty';
		empty.hidden = true;

		// --- live region (count announcements) ---
		var live = document.createElement( 'div' );
		live.className = 'wpca-cmdk-live screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );

		card.appendChild( inputRow );
		card.appendChild( list );
		card.appendChild( empty );
		card.appendChild( live );
		overlay.appendChild( card );
		document.body.appendChild( overlay );

		els = {
			overlay: overlay,
			card: card,
			input: input,
			list: list,
			empty: empty,
			live: live,
		};

		wireEvents();
		built = true;
	}

	function searchSvg() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'width', '20' );
		svg.setAttribute( 'height', '20' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '2' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		var circle = document.createElementNS( ns, 'circle' );
		circle.setAttribute( 'cx', '11' );
		circle.setAttribute( 'cy', '11' );
		circle.setAttribute( 'r', '7' );
		var line = document.createElementNS( ns, 'line' );
		line.setAttribute( 'x1', '21' );
		line.setAttribute( 'y1', '21' );
		line.setAttribute( 'x2', '16.65' );
		line.setAttribute( 'y2', '16.65' );
		svg.appendChild( circle );
		svg.appendChild( line );
		return svg;
	}

	// ---- discoverable trigger (admin-bar button) ---------------------------

	// Render a real, focusable trigger so keyboard/AT users who don't know the
	// shortcut still have an affordance. Lives in #wpadminbar so it sits in the
	// natural tab order of the toolbar; falls back to a fixed button if absent.
	function buildTrigger() {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wpca-cmdk-trigger';
		btn.setAttribute( 'aria-haspopup', 'dialog' );
		btn.setAttribute( 'aria-label', t( 'openLabel', 'Open command palette' ) );
		btn.title = t( 'openLabel', 'Open command palette' );

		var srLabel = document.createElement( 'span' );
		srLabel.className = 'screen-reader-text';
		srLabel.textContent = t( 'openLabel', 'Open command palette' );

		var glyph = document.createElement( 'span' );
		glyph.className = 'wpca-cmdk-trigger-glyph';
		glyph.setAttribute( 'aria-hidden', 'true' );
		glyph.textContent = triggerGlyph();

		btn.appendChild( srLabel );
		btn.appendChild( glyph );
		btn.addEventListener( 'click', function () {
			openPalette();
		} );

		var bar = document.getElementById( 'wp-admin-bar-top-secondary' ) ||
			document.getElementById( 'wpadminbar' );

		if ( bar && 'UL' === bar.tagName ) {
			var li = document.createElement( 'li' );
			li.className = 'wpca-cmdk-trigger-item';
			li.appendChild( btn );
			bar.insertBefore( li, bar.firstChild );
		} else if ( bar ) {
			bar.appendChild( btn );
		} else {
			btn.classList.add( 'wpca-cmdk-trigger--floating' );
			document.body.appendChild( btn );
		}
	}

	// ---- rendering ---------------------------------------------------------

	function render( query ) {
		rows = filter( query );
		rowNodes = [];
		active = rows.length ? 0 : -1;

		// Clear list.
		while ( els.list.firstChild ) {
			els.list.removeChild( els.list.firstChild );
		}

		// aria-expanded must reflect whether the popup actually presents options.
		els.input.setAttribute( 'aria-expanded', rows.length ? 'true' : 'false' );

		if ( ! rows.length ) {
			renderEmpty();
			els.input.removeAttribute( 'aria-activedescendant' );
			announce( 0 );
			return;
		}

		els.empty.hidden = true;
		els.list.hidden = false;

		for ( var i = 0; i < rows.length; i++ ) {
			els.list.appendChild( buildRow( rows[ i ], i ) );
		}

		setActive( 0, false );
		announce( rows.length );
	}

	function buildRow( row, index ) {
		var item = row.item;
		var li = document.createElement( 'li' );
		li.className = 'wpca-cmdk-row';
		li.id = ID_BASE + index;
		li.setAttribute( 'role', 'option' );
		li.setAttribute( 'aria-selected', 'false' );

		var label = document.createElement( 'span' );
		label.className = 'wpca-cmdk-label';
		appendHighlighted( label, item.label, row.ranges );

		li.appendChild( label );

		if ( item.parent ) {
			var suffix = document.createElement( 'span' );
			suffix.className = 'wpca-cmdk-suffix';
			suffix.textContent = item.parent; // textContent — never innerHTML.
			li.appendChild( suffix );
		}

		// Hover SYNCS the active row (keeps mouse + keyboard coherent), it does not
		// itself navigate. Click navigates.
		li.addEventListener( 'pointermove', function () {
			if ( active !== index ) {
				setActive( index, false );
			}
		} );
		li.addEventListener( 'click', function () {
			activate( index );
		} );

		rowNodes[ index ] = li;
		return li;
	}

	// Wrap matched character ranges in <mark>, built from DOM text nodes only.
	function appendHighlighted( parent, label, ranges ) {
		if ( ! ranges || ! ranges.length ) {
			parent.textContent = label;
			return;
		}

		var cursor = 0;
		for ( var i = 0; i < ranges.length; i++ ) {
			var start = ranges[ i ][ 0 ];
			var end = ranges[ i ][ 1 ];
			if ( start > cursor ) {
				parent.appendChild( document.createTextNode( label.slice( cursor, start ) ) );
			}
			var mark = document.createElement( 'mark' );
			mark.className = 'wpca-cmdk-mark';
			mark.textContent = label.slice( start, end );
			parent.appendChild( mark );
			cursor = end;
		}
		if ( cursor < label.length ) {
			parent.appendChild( document.createTextNode( label.slice( cursor ) ) );
		}
	}

	function renderEmpty() {
		els.list.hidden = true;
		while ( els.empty.firstChild ) {
			els.empty.removeChild( els.empty.firstChild );
		}
		var msg = document.createElement( 'p' );
		msg.className = 'wpca-cmdk-empty-text';
		// Static localized message — no user input is ever echoed by the palette.
		msg.textContent = t( 'noResults', 'No results found' );
		els.empty.appendChild( msg );
		els.empty.hidden = false;
	}

	function announce( count ) {
		var tmpl = t( 'resultsCount', '%s results available' );
		els.live.textContent = tmpl.replace( '%s', String( count ) );
	}

	// ---- active-row management --------------------------------------------

	function setActive( index, scroll ) {
		if ( ! rows.length ) {
			return;
		}
		if ( index < 0 ) {
			index = rows.length - 1;
		} else if ( index >= rows.length ) {
			index = 0;
		}

		if ( active > -1 && rowNodes[ active ] ) {
			rowNodes[ active ].classList.remove( 'is-active' );
			rowNodes[ active ].setAttribute( 'aria-selected', 'false' );
		}

		active = index;
		var node = rowNodes[ active ];
		if ( node ) {
			node.classList.add( 'is-active' );
			node.setAttribute( 'aria-selected', 'true' );
			els.input.setAttribute( 'aria-activedescendant', node.id );
			if ( false !== scroll ) {
				node.scrollIntoView( { block: 'nearest' } );
			}
		}
	}

	function activate( index ) {
		var row = rows[ index ];
		if ( ! row || ! row.item || ! row.item.url ) {
			return;
		}

		// Read-only GET navigation. The server already ran the URL through
		// esc_url_raw(); re-check the protocol here as defense-in-depth so a
		// javascript:/data: URL could never reach location.assign() even if the
		// PHP layer regressed.
		var safe = row.item.url;
		try {
			var u = new URL( row.item.url, window.location.origin );
			if ( 'http:' !== u.protocol && 'https:' !== u.protocol ) {
				return;
			}
			safe = u.href;
		} catch ( e ) {
			return;
		}

		window.location.assign( safe );
	}

	// ---- background isolation (full inert for aria-modal) ------------------

	// #wpadminbar is a SIBLING of #wpwrap (both direct children of <body>), so hiding
	// only #wpwrap leaves the toolbar in the AT tree behind the modal. Hide every
	// direct body child except our own overlay, and restore exactly that set on close.
	function hideBackground() {
		hidden = [];
		var children = document.body.children;
		for ( var i = 0; i < children.length; i++ ) {
			var node = children[ i ];
			if ( node === els.overlay || node.classList.contains( 'wpca-cmdk-trigger' ) ) {
				continue;
			}
			if ( 'true' !== node.getAttribute( 'aria-hidden' ) ) {
				node.setAttribute( 'aria-hidden', 'true' );
				hidden.push( node );
			}
		}
	}

	function restoreBackground() {
		for ( var i = 0; i < hidden.length; i++ ) {
			hidden[ i ].removeAttribute( 'aria-hidden' );
		}
		hidden = [];
	}

	// ---- open / close ------------------------------------------------------

	function openPalette() {
		if ( ! built ) {
			build();
		}
		if ( open ) {
			return;
		}

		returnFocus = document.activeElement;
		open = true;

		hideBackground();
		document.body.classList.add( 'wpca-cmdk-lock' );

		els.input.value = '';
		render( '' );
		els.overlay.classList.add( 'is-open' );

		// Focus after the open class so the entrance transition runs.
		els.input.focus();
	}

	function closePalette() {
		if ( ! open ) {
			return;
		}
		open = false;

		els.overlay.classList.remove( 'is-open' );
		document.body.classList.remove( 'wpca-cmdk-lock' );
		restoreBackground();

		// Restore focus to where it was. If that element left the DOM, fall back to a
		// known-focusable target (body is not reliably focusable across browsers).
		if ( returnFocus && document.contains( returnFocus ) && returnFocus.focus ) {
			returnFocus.focus();
		} else {
			focusFallback();
		}
		returnFocus = null;
	}

	function focusFallback() {
		var target = document.querySelector( '.wpca-cmdk-trigger' ) ||
			document.getElementById( 'wpcontent' ) ||
			document.getElementById( 'wpadminbar' );

		if ( target ) {
			if ( ! target.hasAttribute( 'tabindex' ) ) {
				target.setAttribute( 'tabindex', '-1' );
			}
			target.focus();
		}
	}

	// ---- event wiring ------------------------------------------------------

	function wireEvents() {
		// Track whether a pointer press STARTED on the overlay scrim, so a drag that
		// began in the input and released on the scrim does not dismiss.
		var downOnOverlay = false;

		els.overlay.addEventListener( 'pointerdown', function ( e ) {
			downOnOverlay = e.target === els.overlay;
		} );
		els.overlay.addEventListener( 'pointerup', function ( e ) {
			if ( downOnOverlay && e.target === els.overlay ) {
				closePalette();
			}
			downOnOverlay = false;
		} );

		// Clicks inside the card never bubble to the scrim dismiss.
		els.card.addEventListener( 'pointerdown', function ( e ) {
			e.stopPropagation();
		} );

		var debounce;
		els.input.addEventListener( 'input', function () {
			window.clearTimeout( debounce );
			var value = els.input.value;
			debounce = window.setTimeout( function () {
				render( value );
			}, 30 );
		} );

		// Keyboard model lives on the input (it always keeps real focus).
		els.input.addEventListener( 'keydown', function ( e ) {
			switch ( e.key ) {
				case 'ArrowDown':
					e.preventDefault();
					setActive( active + 1, true );
					break;
				case 'ArrowUp':
					e.preventDefault();
					setActive( active - 1, true );
					break;
				case 'Home':
					e.preventDefault();
					setActive( 0, true );
					break;
				case 'End':
					e.preventDefault();
					setActive( rows.length - 1, true );
					break;
				case 'Enter':
					e.preventDefault();
					if ( active > -1 ) {
						activate( active );
					}
					break;
				case 'Escape':
					e.preventDefault();
					closePalette();
					break;
				case 'Tab':
					// Focus trap: only one focusable element exists, so swallow Tab.
					e.preventDefault();
					break;
				default:
					break;
			}
		} );
	}

	// ---- global open trigger ----------------------------------------------

	document.addEventListener( 'keydown', function ( e ) {
		var combo = IS_MAC ? e.metaKey : e.ctrlKey;
		if ( ! combo || e.altKey || e.shiftKey ) {
			return;
		}

		// Match the PHYSICAL K key via e.code (layout-independent: always 'KeyK'),
		// with e.key kept as a fallback. Non-Latin layouts (Persian) emit a Persian
		// character for e.key, so a key-only check would make the shortcut unreachable
		// for exactly the audience this plugin ships fa_IR for.
		var isK = 'KeyK' === e.code || 'k' === ( e.key || '' ).toLowerCase();
		if ( ! isK ) {
			return;
		}

		// Stay out of editors we do not own (block editor iframe, contenteditable).
		var ae = document.activeElement;
		if ( ae && ( ae.isContentEditable || 'IFRAME' === ae.tagName ) ) {
			return;
		}

		e.preventDefault();
		if ( open ) {
			closePalette(); // second Cmd/Ctrl-K toggles closed.
		} else {
			openPalette();
		}
	} );

	// The keydown listener and trigger button intentionally live for the page
	// lifetime (a full admin page load), so there is no teardown; detached row
	// nodes from rebuilds are GC-eligible once the list is cleared.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', buildTrigger );
	} else {
		buildTrigger();
	}
}() );
