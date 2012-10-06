<?php

/**
 *
* @author David Raison <david@raison.lu>
* @file smwparser.class.php
* @version 0.1
*
*/

class SourceParser {

	public static function retrieve( $format, $dsn ) {
		global $config;
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
				$config['debug'] && printf("Loading data from url %s\n", $dsn );
				$input = $dsn;
			break;
			}

			$source = new WikiApiClient;
			$data = $source->getInitialData( $input );
		break;
		case 'pdo':
			// in this case, we need to map the fields from the database to the properties we're accustomed to use
			try {
				$source = new PDOMapper;
				$data = $source->getEventData();
			} catch ( Exception $e ) {
				print($e->getTraceAsString());
			}
		break;
		}

		return $data;
	}

}
