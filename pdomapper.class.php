<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file pdomapper.class.php
 * @ingroup plurioparser
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
			printf( "FATAL ERROR: %s. ABORTING\n" , $e->getMessage());
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

		// fields to be retrieved (missing: has URL, has ticket url, has subtitle)
		$keys = array(
			'IDAct',
			'CAST(nom as varchar(50)) AS nom',		// label        
			'DateDebut',                                    // Has StartDate
			'DateFin',                                      // Has EndDate
			'Heure',
			'Heure2',
			'CAST(Description AS text) AS Description',	// Has description
                        'CAST(DescriptionFR AS text) AS DescriptionFR',
			'CAST(Categorie AS varchar(50)) AS Categorie',	// Has_category
			'CAST(cat1 AS varchar(14)) AS cat1',
			'CAST(cat2 AS varchar(14)) AS cat2',
			'CAST(cat3 AS varchar(14)) AS cat3',
			'CAST(TrancheAge AS varchar(12)) AS TrancheAge',	// NO CORRESPONDING ITEM in smw
			'IDlieu',                                       // Has location	(yes, we use an ID here)
			'CAST(Lieu AS varchar(24)) AS Lieu',		// or not?
			'CAST(Organisateur AS varchar(50)) AS Organisateur',	// Has organizer
			'CAST(Image AS varchar(24)) AS Image', 	// Has picture
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
                    // returning plurio IDs, see addToGuide() in organisation.class and the change in event.class
			switch( $entity ) {
                            case "'natur musée'":
                                return 29699;   // FIXME, Building ID??
                            break;
                            case 'Panda-Club':
                                return 46093;
                                break;
                            case 'Science-Club':
                                return 46095;
                                break;
                        }
		} elseif ( get_class( $caller ) == 'Building' ) {
			return $entity;
		} else {
			$pdoitem = 'PDOEventID';
			$table = $this->_tEvents;
			$keys = array( 'IDAct' );
			$filter = array( 'nom' => $entity );
			$data = $this->_doQuery( $keys, $filter, $table, $pdoitem );
			return $data[0]->IDAct;
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

	// we're just adding the url from the config 
	// and modifying the filename to say ...HQ.jpg
	public function fetchPictureInfo( $file, $category ){
		global $config;
		$path = $config['media.path'];
                $path = (substr( $path, -1) == '/' ) ? $path : $path . '/';
		return $path . $category . '/' . $file;
	}

	public function fetchOrganisationInfo( $org ) {
		global $config;

                   // FIXME (one config per organisation?)
                if ($org == "'natur musée'" || "Panda-Club" || "Science-Club" ) {
			// get organisation info from config file instead FIXME FIXME FIXME
			$info = new StdClass;
			$info->has_contact = array( $config['org.contact'] );
			$info->has_description = array( $config['org.description'] );
			$info->has_location = array( 234 );	// natur musée	// FIXME
			$info->has_picture = array( $config['org.logo'] );
			$info->has_subtitle = array('Musée national d\'histoire naturelle');	//FIXME
			$info->url = array( $config['org.url'] );
			return $info;
		}
	}

	/**
	 * Assemble and execute a query against the cache or againt the pdo database
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
					printf("Query took %s seconds\n", round( $exectime, 2 ) );
				}
				return $data;
			} else throw new Exception( sprintf( "Could not retrieve data from database. Query: %s\n", $query ) );
		}
	}

}

class PDOEventID {
}

class PDOEventItem {
	/**
	 * This is where we're doing the actual mapping to match the smw object
	 */

	private $_pandaSignUp = 'http://www.panda-club.lu/umeldung/login/';
	private $_pandaMail = 'panda-club@mnhn.lu';
	private $_scienceSignUp = 'http://www.science-club.lu/umeldung/login/';
	private $_scienceMail = 'science-club@mnhn.lu';

	//FIXME: this mapping would be much better off in the config file!!
	public function __construct() {
		// setting data as first element of an array is necessary since the mediawiki
		// json export has these things exported as arrays as well. Both need to have the same structure
		// and since we're already mapping these to smw, we're changing everything here and nothing there
		!empty( $this->nom ) && $this->label = $this->_ic( $this->nom );

		!empty( $this->DateDebut ) && $this->startdate[0] = $this->_createDateArray( $this->_ic( $this->DateDebut ), $this->_ic( $this->Heure ) );
		if ( !empty( $this->DateFin ) ) {
			$this->enddate[0] = $this->_createDateArray( $this->_ic( $this->DateFin ), $this->_ic( $this->Heure2 ) );
		} else {
			$this->enddate[0] = $this->_createDateArray( $this->_ic( $this->DateDebut), $this->_ic( $this->Heure2) );
		}

		!empty( $this->Description ) && $this->has_description[0] = $this->_ic( $this->Description );
                !empty( $this->DescriptionFR ) && $this->has_description[1] = $this->_ic( $this->DescriptionFR );

		for( $i = 1; $i < 4; $i++ ) {
			$val = 'cat' . $i;
			!empty( $this->$val ) && $this->category[] = $this->_ic( $this->$val );
		}

               // data relative to the category
		if( !empty( $this->Categorie ) ) {
			$this->has_organizer[0] = $this->_ic( $this->Categorie );
                    if( $this->Categorie == 'MNHN' ) $this->has_organizer[0] = "'natur musée'";
			$this->has_ticket_url[0] = ( $this->Categorie == 'Panda-Club' ) ? $this->_pandaSignUp : $this->_scienceSignUp;
			$this->has_contact[0] = ( $this->Categorie == 'Panda-Club' ) ? $this->_pandaMail : $this->_scienceMail;
		} else $this->has_organizer[0] = "'natur musée'";

		!empty( $this->TrancheAge ) && $this->is_event_of_type[0] = $this->_ic( $this->TrancheAge );
		!empty( $this->IDlieu ) && $this->has_location_id[0] = $this->_ic( $this->IDlieu );
		!empty( $this->Lieu ) && $this->has_location[0] = $this->_ic( $this->Lieu );
                !empty( $this->Prix ) && $this->has_cost[0] = $this->Prix;
                
                // For organizers other than "natur musée", add a snippet to the description text.
                if( !empty( $this->Organisateur ) && $this->_ic( $this->Organisateur) != "'natur musée'" ) {
                    $this->has_description[0] .= "<br/><p>Mit freundlicher Unterstützung von "
                                                  . $this->_ic( $this->Organisateur )
                                                  . "</p>";
                    $this->has_description[1] .= "<br/><p>Avec le soutien de "
                                                  . $this->_ic( $this->Organisateur )
                                                  . "</p>";
                }
                /*
                 * Science-Club
                 * Workshop / 13-15 Joer (Anmeldung erforderlich / Inscription obligatoire)
                 */
                $this->has_subtitle[0] = $this->has_organizer[0]
                        . "<br/>" . ucfirst( $this->_ic( $this->cat1 ) )
                        . " / " . $this->TrancheAge . " Joer"
                        . " / (Anmeldung erforderlich / Inscription obligatoire)";

		// FIXME: this is not compatible with SMW!! FIXME FIXME
		// either change the wikiapiclient class to do this too or remove this and let the Picture class do the work!
		!empty( $this->Image ) && $this->has_picture[0] = $this->_setHQPicture( $this->Image );
	}


	/**
	 * Using convert() might be an option, but it seems that using php conversion will be easier
	 * http://www.mssqltips.com/sqlservertip/1145/date-and-time-conversions-using-sql-server/
	 * See also wikiApiClient.class.php --> _parseDate() method
	 */
	private function _createDateArray( $date, $time ) {
		$datestring = strtotime( $date );
		$date = date( "Y-m-d", $datestring );

		$timestring = strtotime( $time );
		$time = date( "H:i", $timestring );
		$datetime = array( $date, $time );
		return $datetime;
	}

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
            global $config;
            return ( $config['data.iconv'] ) ? iconv( 'ISO-8859-1', 'UTF-8', $val) : $val;
	}

	private function _setHQPicture( $name ) {
		return substr($name, 0, -4) . 'HQ.jpg';
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
		$this->has_localDescription[0] = ( !empty($this->affichageSN) ) ? $this->_ic( $this->affichageSN ) : $this->label;
	}

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
            global $config;
            return ( $config['data.iconv'] ) ? trim( iconv( 'ISO-8859-1', 'UTF-8', $val) ) : $val;
	}

}
