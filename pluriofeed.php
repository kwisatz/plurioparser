<?php

/**
 * Plurioparser
 *
 * Parses data from various sources and generates plurio.net XML data
 *
 * @author David Raison <david@raison.lu>
 * @file pluriofeed.php
 * @ingroup plurioparser
 * @version 1.1
 */

define('DS', DIRECTORY_SEPARATOR);

// first get the config data
$config = parse_ini_file( 'config/config.ini', false );

$config['debug'] && $time_start = time();

function __autoload( $name ) {
	if ( strpos( $name, '_' ) ) { 
		$parts = explode('_', $name );
		$dir = strtolower( $parts[0] ) . DS;
		$name = $parts[1];
	} else $dir = '';
	$filename = dirname( __FILE__ ) . DS . $dir . strtolower( $name ) . '.class.php';
	if (file_exists( $filename ) ) {
		require_once $filename;
	} else {
		throw new Exception( sprintf( 'Couldn\'t require component "%s"', $name ), 405 );
	}
}

// main()
try {
	$data = SourceParser::retrieve( $config['data.format'], $config['data.source'] );
	$plurio = new PlurioXMLBuilder( $data );
	$xmlFeed = $plurio->createFeed();
} catch ( Exception $e ) {
	print($e->getMessage());
}

// HEADERS?!
//$plurio->send_headers();
file_put_contents( $config['data.dest'], $xmlFeed );


// More debug
if( $config['debug'] ){
	$exectime = time() - $time_start;
	//print( $xmlFeed );
	printf("Execution took %d seconds\n", $exectime);
}

?>
