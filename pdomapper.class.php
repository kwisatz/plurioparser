<?php

/**
 *
 //$res->setFetchMode( PDO::FETCH_CLASS, 'PDOItem', $keys);
 //$data = $res->fetchAll(PDO::FETCH_ASSOC);
 //$data = $res->fetchAll(PDO::FETCH_CLASS);
 //$data = $res->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_FUNC, 'PDOMapper::iconvMap');
 //$data = $res->fetchAll(PDO::FETCH_FUNC, 'PDOMapper::iconvMap2');
 //$data = array_map( 'PDOMapper::iconvMap', $data);
 *
 */

class PDOMapper implements Interface_DataSource {

	private $_dbh;	// db handle
	private $_tEvents;
	private $_tLocations;

	protected static $_dbData;


	public function __construct(){
		global $config;

		try {
			$this->_dbh = new PDO( $config['data.source'], $config['data.user'], $config['data.pass'] );
			$this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			print('ERROR: ' . $e->getMessage());
			exit(0);
		}

		// set event and location tables
		$this->_tEvents = $config['tables.events'];
		$this->_tLocations = $config['tables.locations'];

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
			'Description',	// Has description
			'Categorie',	// Is Event of Type
			'IDlieu',	// Has location	(yes, we use an ID here)
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
			throw new Exception( "PDOMapper does not support Organisation queries.\n", 001 );
		} elseif ( get_class( $caller ) == 'Building' ) {
			return $entity;
		} else {
			$pdoitem = 'PDOEventItem';
			$table = $this->_tEvents;
			$keys = array( 'IDAct' );
			$filter = array( 'nom' => $entity );
			$data = $this->_doQuery( $keys, $filter, $table, $pdoitem);
			var_dump("getIdFor", $data);
		}

	}

	/**
	 * Retrieve information on a location using the database connection
	 * hmm... would be better to use the ID... but that would require changes in the building class...
	 * FIXME: missing: picture, url, email, phone)
	 */
	public function fetchLocationInfo( $idlieu ) {
		$keys = array(
			'lieu',		// label
			'numero',	// has address
			'rue',		// has address
			'cp',		// has city
			'ville',	// has city
			'pays',		// has country
			'affichageSN'	// NO CORRESPONDING ITEM YET (has_localDescription)
			);
		$filter = array( 'IDlieu' => $idlieu );
		$resultset = $this->_doQuery( $keys, $filter, $this->_tLocations, 'PDOLocationItem' );
		return $resultset[0];
	}

	public function fetchOrganisationInfo( $org ) {
		global $config;

		try {
			// query database if an organisation table is available
			/*
			 * has contact
			 * has description
			 * has location
			 * has picture
			 * has subtitle
			 * has url
			 */
			throw new Exception("Organisation info from PDOMapper not yet supported", "002");
		} catch (Exception $e) {
			if( $e->getCode() == "002" && $org == "'natur musée'") {
				// get organisation info from config file instead FIXME FIXME FIXME
				$info = new StdClass;
				$info->has_contact = array( $config['org.contact'] );
				$info->has_description = array( $config['org.description'] );
				$info->has_location = array( 234 );	// natur musée
				$info->has_picture = array( $config['org.logo'] );
				$info->has_subtitle = array('Musée national d\'histoire naturelle');
				$info->url = array( $config['org.url'] );
				return $info;
			}
		}
	}

	/**
	 *
	 */
	private function _doQuery( $keys, $filter, $table, $pdoitem = 'PDOEventItem', $options = null ) {
		global $config;

		// we will probably need to store information seperately for every combination of filters and keys
		$idxhash = md5( implode('|', array_merge( $keys, $filter ) ) );

		if( array_key_exists( $idxhash, self::$_dbData ) ) {
			if ( $config['debug'] ) printf("Retrieved data of type %s from cache. idxhash: %s \o/\n", $pdoitem, $idxhash );
			return self::$_dbData[ $idxhash ];
		} else {
			if ( $config['debug'] ) printf( "Retrieving fresh data of type %s from database. idhash: %s\n", $pdoitem, $idxhash );
			$where = array();
			foreach ( $filter as $key => $value ) {
				if ( is_string( $value ) || is_int( $value ) ) {
					$where[] = sprintf("%s = '%s'", $key, $value);
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
				return $data;
			} else throw new Exception( sprintf( "Could not retrieve data from database. Query: %s\n", $query ) );
		}
	}

}

class PDOEventItem {
	/**
	 * This is where we're doing the actual mapping to match the smw object
	 */

	//FIXME: this mapping would be much better off in the config file!!
	public function __construct() {
		// setting data as first element of an array is necessary since the mediawiki
		// json export has these things exported as arrays as well. Both need to have the same structure
		// and since we're already mapping these to smw, we're changing everything here and nothing there
		$this->label = $this->_ic( $this->nom );

		$this->startdate[0] = $this->_createDateArray( $this->_ic( $this->DateDebut ) );
		$this->enddate[0] = $this->_createDateArray( $this->_ic( $this->DateFin ) );

		$this->has_description[0] = $this->_ic( $this->Description );
		$this->category = $this->_ic( $this->Categorie );
		$this->has_location[0] = $this->_ic( $this->IDlieu );
		$this->has_organizer[0] = $this->_ic( $this->Organisateur );
		$this->has_cost = $this->_ic( $this->Prix );

		// FIXME: this is not compatible with SMW!! FIXME FIXME
		// either change the wikiapiclient class to do this too or remove this and let the Picture class do the work!
		$this->has_picture[0] = $this->_setPicturePath( $this->Image );
	}

	/**
	 * Using convert() might be an option, but it seems that using php conversion will be easier
	 * http://www.mssqltips.com/sqlservertip/1145/date-and-time-conversions-using-sql-server/
	 * See also wikiApiClient.class.php --> _parseDate() method
	 */
	private function _createDateArray( $datetime ) {
		$timestring = strtotime( $datetime );
		$date = date( "Y-m-d", $timestring );
		$time = date( "H:i", $timestring );
		$datetime = array( $date, $time );
		return $datetime;
	}

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
		return iconv( 'ISO-8859-1', 'UTF-8', $val);
	}

	private function _setPicturePath( $name ) {
		global $config;

		$path = $config['media.path'];
		$file = substr($name, 0, -4) . 'HQ.jpg';
		return $path . $file;
	}

}

class PDOLocationItem {

	public function __construct() {
		$this->label = $this->_ic( $this->lieu );
		$this->has_number = $this->_ic( $this->numero );
		$this->has_address = $this->_ic( $this->rue );
		$this->has_zipcode = $this->_ic( $this->cp );
		$this->has_city = $this->_ic( $this->ville );
		$this->has_country = $this->_ic( $this->pays );
		$this->has_localDescription[0] = $this->_ic( $this->affichageSN );
	}

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
		return iconv( 'ISO-8859-1', 'UTF-8', $val);
	}

}
