<?php
/**
 * 
 * Class that interfaces with a pdo driver on the local system
 * Can connect to any database supported by the PDO driver and its subdrivers
 * The driver to be used is determined by the source DSN given in config.ini
 * 
 * @author David Raison <david@raison.lu>
 * @file .class.php
 * @ingroup plurioparser
 * @version 1.2
 */

namespace DataSource\PDO;
use \Interface\DataSource as DSource;

class Client implements DSource {

	private $_dbh;	// db handle
	protected static $_dbData;  // ???

	public function __construct(){
		global $config;

		try {
			$this->_dbh = new PDO( $config['data']['source'], $config['data']['user'], $config['data']['pass'] );
			$this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			printf( "FATAL ERROR: %s. ABORTING\n" , $e->getMessage());
			exit(0);
		}

                // build the data cache
		if( !isset( self::$_dbData ) )
			self::$_dbData = array();
	}

	/**
	 * Retrieve initial activity data from the database
	 * Build the appropriate query
	 */
	public function getInitialData() {
		// restrictions to be applied to the data queried
		$filter = array(
			'Internet' => 1,
			'DateDebut' => array('>', date( 'Y-m-d H:i:s' ) )
		);

		// FIXME
		print( "DEBUG DEBUG DEBUG :: overwriting DateDebut in " . __FILE__ . " on line " . __LINE__ . "\n");
		$filter = array( 'Internet' => 1 );

		// fields to be retrieved (missing: has URL, has ticket url, has subtitle)
		$keys = array(
			'IDAct',
			'nom',		// label
			'DateDebut', 	// Has StartDate
			'DateFin',	// Has EndDate
			'Heure',
			'Heure2',
			'Description',	// Has description
			'Categorie',	// Has_category
			'cat1',
			'cat2',
			'cat3',
			'TrancheAge',	// NO CORRESPONDING ITEM in smw
			'IDlieu',	// Has location	(yes, we use an ID here)
			'Lieu',		// or not?
			'Organisateur',	// Has organizer
			'Image', 	// Has picture
			'Prix'		// Has cost
		);

		$options = "ORDER BY DateDebut ASC";
		$initial = $this->_doQuery( $keys, $filter, $this->_tEvents, 'PDOEventItem', $options );
		$wrapper = new stdClass;
		$wrapper->items = $initial;
		return $wrapper;
	}

	/**
	 * FIXME: this is ugly, very ugly
	 */
	public function getIdFor( $entity, $caller ) {
		global $config;

		if ( $config['debug'] ) printf( "Method %s called for entity %s of type %s\n", __METHOD__, $entity, get_class( $caller ) );
		if ( get_class( $caller ) == 'Organisation' ) {
			return md5($entity);
		} elseif ( get_class( $caller ) == 'Building' ) {
			return $entity;
		} else {
			$pdoitem = 'PDOEventItem';
			$table = $this->_tEvents;
			$keys = array( 'IDAct' );
			$filter = array( 'nom' => $entity );
			$data = $this->_doQuery( $keys, $filter, $table, $pdoitem );
			return $data[0]->IDAct;
		}

	}

	/**
	 *
	 */
	private function _doQuery( $keys, $filter, $table, $pdoitem = 'PDOEventItem', $options = null ) {
		global $config;

		$config['debug'] && $time_start = microtime(true);

		// we will probably need to store information seperately for every combination of filters and keys
		$idxhash = md5( implode('|', array_merge( $keys, $filter ) ) );

		if( array_key_exists( $idxhash, self::$_dbData ) ) {
			if ( $config['debug'] ) printf("Retrieved data of type %s from cache. idxhash: %s \o/\n", $pdoitem, $idxhash );
			return self::$_dbData[ $idxhash ];
		} else {
			if ( $config['debug'] ) printf( "Retrieving fresh data of type %s from database. idxhash: %s\n", $pdoitem, $idxhash );
			$where = array();
			foreach ( $filter as $key => $value ) {
				if ( is_string( $value ) || is_int( $value ) ) {
					$where[] = sprintf("%s = '%s'", $key, iconv( 'UTF-8','ISO-8859-1', $value ) );	// FIXME
				} elseif( is_array( $value ) ) {
					$where[] = sprintf("%s %s '%s'", $key, $value[0], $value[1]);
				} else throw new Exception( sprintf("Got an invalid filter object: %s\n", print_r( $value ) ) );
			}	
			$template = 'SELECT %1$s FROM %2$s WHERE %3$s %4$s;';
			$query = sprintf( $template, 
				implode( ', ',$keys ), 
				$table, 
				implode( ' AND ', $where ),
				$options
			);
			$res = $this->_dbh->query( $query );

			$data = $res->fetchAll( PDO::FETCH_CLASS, $pdoitem );

			if ( $data ) {
				self::$_dbData[ $idxhash ] = $data;
				if ( $config['debug'] ) {
					$exectime = microtime(true) - $time_start;
					printf("Query took %s seconds\n", $exectime);
				}
				return $data;
			} else throw new Exception( sprintf( "Could not retrieve data from database. Query: %s\n", $query ) );
		}
	}

}

?>