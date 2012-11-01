<?php

/**
 *
* @author David Raison <david@raison.lu>
* @file sourceparser.class.php
* @version 0.1
*
*/

namespace Core\Lib;

class SourceParser {

	public static function retrieve( $format, $dsn ) {
            $config = parse_ini_file( dirname('/apps/' . $app . 'config/config.ini' ) );
            //$config['data']['format']
            //$config['data']['source']
                    
		switch ( $format ) {
		case 'smw':
			$parts = parse_url( $dsn );
			$source = $parts['scheme'];
			$type = $parts['user'];
			$content = $parts['host'];
			$rawpath = $parts['path'];

			switch ( $source ) {
			case 'file':
				try {
					// currently ignoring $content and just presuming it to be a url
					$filename = basename( $rawpath );
					$filepath = dirname(__FILE__) . DS . 'config' . DS . $filename;
					$config['debug'] && printf("Retrieving url from file \"%s\"\n", $filepath );
					if ( $type === 'php' ) {
						$cmd = sprintf('`which php` %s', $filepath );
						$input = shell_exec( $cmd );
					} elseif ( $type === 'plain' ) {
						$input = file_get_contents( $filepath );
					}
				} catch (Exception $e) {
					print($e->getTraceAsString());
				}
			break;
			case 'http':
				( $config['verbosity'] > 2 ) && printf("Loading data from url %s\n", $dsn );
				throw new SPException( "http source not yet implemented", 405 );
			break;
			}

			$source = new \DataSource\MediaWiki\ApiClient;

		break;
		case 'pdo':
			try {
				$source = new \DataSource\PDO\Client;
                                $firestarter = new \Apps\$app\FireStarter;
			} catch ( Exception $e ) {
				print( $e->getMessage() . "\n" );
				print( $e->getTraceAsString() . "\n" );
			}
		break;
		default:
			throw new SPException( sprintf('No such datasource adapter: %s', $format) );
		break;
		}

                try {
                    $data = $source->getInitialData( );
                    return $data;
                } catch ( Exception $e ) {
                    print('FATAL DATA ERROR' . $e->getMessage() );
                    exit(0);
                }
	}
}

?>