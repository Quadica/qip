/**
 * External dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'batch-creator': path.resolve( __dirname, 'assets/js/src/batch-creator/index.js' ),
		'engraving-queue': path.resolve( __dirname, 'assets/js/src/engraving-queue/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/js/build' ),
	},
};
