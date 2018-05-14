#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Script example.php
$shortopts = "d::";
$longopts  = [ "data-file::" ];
$options   = getopt( $shortopts, $longopts );

$data_file = isset( $options['d'] ) && file_exists( $options['d'] )
	? $options['d']
	: ( isset( $options['data-file'] )
	    && file_exists( $options['data-file'] ) ? $options['data-file'] : null );

$data_file = $data_file ?? '/var/lib/midnite-modbusd/status/data.txt';

if ( ! file_exists( $data_file ) ) {
	throw new Exception( 'Invalid data file.' );
}

if ( ! is_readable( $data_file ) ) {
	throw new Exception( 'Cannot read data file.' );
}

$Classic = new Gecka\Midnite\Classic();
$Classic->print_json( file_get_contents( $data_file ) );