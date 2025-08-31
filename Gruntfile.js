module.exports = function(grunt) {
    
    // Get version from main PHP file
    const fs = require('fs');
    const phpContent = fs.readFileSync('restorewp.php', 'utf8');
    const versionMatch = phpContent.match(/Version:\s*([0-9.]+)/);
    const version = versionMatch ? versionMatch[1] : '1.0.0';
    
    grunt.initConfig({
        
        // Clean build directory
        clean: {
            build: ['build/'],
            release: ['releases/']
        },
        
        // Copy files to build directory
        copy: {
            build: {
                files: [{
                    expand: true,
                    src: [
                        '**/*',
                        '!node_modules/**',
                        '!build/**',
                        '!releases/**',
                        '!.git/**',
                        '!.gitignore',
                        '!Gruntfile.js',
                        '!package*.json',
                        '!webpack.config.js',
                        '!test-plugin.php',
                        '!verify-plugin.php',
                        '!debug-permissions.php',
                        '!*.log'
                    ],
                    dest: 'build/restorewp/'
                }]
            }
        },
        
        // Create ZIP file
        compress: {
            release: {
                options: {
                    archive: `releases/restorewp-${version}.zip`,
                    mode: 'zip'
                },
                files: [{
                    expand: true,
                    cwd: 'build/',
                    src: ['**/*'],
                    dest: '/'
                }]
            }
        },
        
        // Create releases directory
        mkdir: {
            releases: {
                options: {
                    create: ['releases']
                }
            }
        }
    });
    
    // Load plugins
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-mkdir');
    
    // Register tasks
    grunt.registerTask('release', [
        'clean:build',
        'mkdir:releases', 
        'copy:build',
        'compress:release',
        'clean:build'
    ]);
    
    // Log version info
    grunt.registerTask('default', function() {
        grunt.log.writeln(`RestoreWP version: ${version}`);
        grunt.log.writeln('Run "grunt release" to create production build');
    });
    
};
