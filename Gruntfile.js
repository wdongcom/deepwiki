
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
		grunt.file.read( 'deepwiki-docs-example/01. About DeepWiki [about].markdown' ),
		'## Installation',
		grunt.file.read( 'deepwiki-docs-example/02. Installation.markdown' ),
		'## Quick Start',
		grunt.file.read( 'deepwiki-docs-example/03. Quick Start.markdown' ),
		'## Configuration',
		grunt.file.read( 'deepwiki-docs-example/04. Configuration.markdown' ),
		'## Directory Structure',
		grunt.file.read( 'deepwiki-docs-example/05. Directory Structure.markdown' ),
		'## Documents Structure',
		grunt.file.read( 'deepwiki-docs-example/06. Documents Structure.markdown' ),
		'## Auto Deployment for Documents',
		grunt.file.read( 'deepwiki-docs-example/07. Auto Deployment for Documents.markdown' ),
		'## URL Rewrite',
		grunt.file.read( 'deepwiki-docs-example/08. URL Rewrite.markdown' ),
		'## Theme Development',
		grunt.file.read( 'deepwiki-docs-example/09. Theme Development.markdown' ),
	];
	grunt.file.write( 'README.md', readme.join( '\n' ) );

};
