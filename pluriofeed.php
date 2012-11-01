<?php

/**
 * Plurioparser
 *
 * Parses data from various sources and generates plurio.net XML data
 *
 * @author David Raison <david@raison.lu>
 * @file pluriofeed.php
 * @ingroup plurioparser
 * @version 3.0
 */

define('DS', DIRECTORY_SEPARATOR);

// first get the main config data
$config = parse_ini_file( 'core/config/config.ini', true );

( $config['debug']['verbosity'] > 1 ) && $time_start = time();

// replaces the deprecated __autoload function in PHP 5.3 (which even seems to be totally ignored with namespace ;))
spl_autoload_register(function ( $className ) {
	// convert namespace to full file path
	$class = str_replace('\\', DS, strtolower( $className ) ) . '.class.php';
	if ( file_exists( $class ) ) {
		require_once $class;
                if ( class_exists( $className ) )
                    return true;
	} else {
		throw new \Exception( sprintf( "Couldn't require component %s\n", $className ), 404 );
	}
});

function main() {
    global $config;
    
    try {
        $apps = \Core\Lib\AppIndexer::index();
        var_dump($apps);
        $data = array();
        foreach( $apps as $app ) {
            $data = \Core\Lib\SourceParser::retrieveDataFor( $app );
            $plurio = new PlurioXMLBuilder( $data );
            $xmlFeed = $plurio->createFeed();
        }
    } catch ( Exception $e ) {
            print($e->getMessage());
    }

    if ( $config['output']['type'] == 'direct' ) {
            $plurio->send_headers();
            print( $xmlFeed );
    } elseif ( $config['output']['type'] == 'file' ) {
            file_put_contents( $config['output']['dest'], $xmlFeed );
    } else throw new Exception( 'output.type must be specified in the config file' ); 

    // More debug
    if( $config['debug']['verbosity'] > 1 ){
            $exectime = time() - $time_start;
            printf("Execution took %d seconds\n", $exectime);
    }
}

main();

?>