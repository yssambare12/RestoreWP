const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		admin: './admin/index.js',
	},
	output: {
		...defaultConfig.output,
		path: __dirname + '/assets/js',
	},
};