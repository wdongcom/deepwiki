
module.exports = function( grunt ) {

	grunt.initConfig( {
		cssmin: {
			combine: {
				files: {
					'deepwiki-themes/default/build.min.css': [
						'deepwiki-themes/default/gfm.css',
						'deepwiki-themes/default/style.css',
					],
				},
			},
		},
		watch: {
			scripts: {
				files: [ 'Gruntfile.js', 'deepwiki-themes/default/*' ],
				tasks: [ 'default' ],
			},
		},
	} );

	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );

	grunt.registerTask( 'default', [ 'cssmin' ] );

	readme = [
		'# DeepWiki',
		grunt.file.read( 'deepwiki-docs/01. About DeepWiki.markdown' ),
		'## Installation',
		grunt.file.read( 'deepwiki-docs/02. Installation.markdown' ),
		'## Quick Start',
		grunt.file.read( 'deepwiki-docs/03. Quick Start.markdown' ),
		'## Configuration',
		grunt.file.read( 'deepwiki-docs/04. Configuration.markdown' ),
		'## URL Rewrite',
		grunt.file.read( 'deepwiki-docs/05. URL Rewrite.markdown' ),
		'## Directory Structure',
		grunt.file.read( 'deepwiki-docs/06. Directory Structure.markdown' ),
		'## Documents Structure',
		grunt.file.read( 'deepwiki-docs/07. Documents Structure.markdown' ),
		'## Theme Development',
		grunt.file.read( 'deepwiki-docs/08. Theme Development.markdown' ),
	];
	grunt.file.write( 'README.md', readme.join( '\n' ) );

};
