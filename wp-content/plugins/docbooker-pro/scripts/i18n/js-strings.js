#!/usr/bin/env node
/**
 * The exact set of strings the JavaScript asks @wordpress/i18n for.
 *
 * The .pot cannot answer this: its merge drops a JS entry whose text already
 * appeared in PHP, so a string used in both places carries only PHP references
 * and would be left out of the JSON the screens read.
 *
 * Needs the gettext-extractor package. It is not a dependency of this plugin;
 * point NODE_PATH at a node_modules that has it, e.g. a family plugin's:
 *   NODE_PATH=../gb-business-manager/node_modules node scripts/i18n/js-strings.js
 */
const path = require( 'path' );
const fs = require( 'fs' );
const { GettextExtractor, JsExtractors } = require( 'gettext-extractor' );

const root = path.resolve( __dirname, '../..' );
const fnNames = [ '__', '_e', '_x', '_ex', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_html_x', 'esc_attr__', 'esc_attr_e', 'esc_attr_x' ];

const extractor = new GettextExtractor();
extractor
	.createJsParser( [ JsExtractors.callExpression( fnNames, { arguments: { text: 0 } } ) ] )
	.parseFilesGlob( 'src/js/**/*.{js,jsx}', { cwd: root } )
	.parseFilesGlob( 'blocks-src/src/**/*.{js,jsx}', { cwd: root, ignore: [ 'blocks-src/src/**/node_modules/**' ] } );

const ids = [ ...new Set( extractor.getMessages().map( ( m ) => m.text ).filter( Boolean ) ) ].sort();
const dest = path.join( __dirname, 'js-strings.json' );
fs.writeFileSync( dest, JSON.stringify( ids, null, 1 ), 'utf8' );
console.log( `${ ids.length } strings used by the JavaScript → ${ path.relative( root, dest ) }` );
