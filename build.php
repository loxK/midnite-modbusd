#!/usr/bin/php -dphar.readonly=0
<?php
$srcDirs = [
    "/vendor",
    "/src/inc",
];

$buildRoot = realpath( __DIR__ );

if ( file_exists( $buildRoot . '/dist/bin/midnite-classic-data.phar' ) ) {
    unlink( $buildRoot . '/dist/bin/midnite-classic-data.phar' );
}

echo "Build phar\n";
$phar = new Phar( $buildRoot . '/dist/bin/midnite-classic-data.phar', 0, 'midnite-classic-data.phar' );
$phar->startBuffering();
foreach ( $srcDirs as $srcRoot ) {

    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( realpath( __DIR__ . $srcRoot ),
        FilesystemIterator::SKIP_DOTS ) );

    foreach ( $iterator as $file ) {

        if ( 0 == strpos( $file->getFilename(), '.' ) ) {
            continue;
        }
        if ( false !== strpos( $file->getFilename(), '.xml.dist' ) ) {
            continue;
        }
        if ( false !== strpos( $file->getPathName(), 'README.md' ) ) {
            continue;
        }
        if ( false !== strpos( $file->getPathName(), 'tests/' ) ) {
            continue;
        }
        if ( false !== strpos( $file->getPathName(), 'Tests/' ) ) {
            continue;
        }
        if ( false !== strpos( $file->getPathName(), 'examples/' ) ) {
            continue;
        }

        $pathName = $file->getPathName();
        if ( 0 === strpos( $pathName, $buildRoot ) ) {
            $pathName = substr( $pathName, strlen( $buildRoot ) );
        }
        $phar->addFromString( $pathName, file_get_contents( $file ) );
    }
}

$content = file_get_contents( __DIR__ . "/src/midnite-classic-data.php" );
$content = preg_replace( '{^#!/usr/bin/env php\s*}', '', $content );
$phar->addFromString( '/src/midnite-classic-data', $content );
$stub
    = <<<EOL
#!/usr/bin/env php
<?php
Phar::mapPhar('midnite-classic-data.phar');
set_include_path( 'phar://midnite-classic-data.phar/' . PATH_SEPARATOR . get_include_path() );
require 'phar://midnite-classic-data.phar/src/midnite-classic-data';
__HALT_COMPILER();
EOL;
$phar->setStub( $stub );
$phar->stopBuffering();

exit( "Build complete\n" );
