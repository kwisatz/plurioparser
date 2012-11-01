<?php

namespace PlurioParser\DataSource\PDO;

class Building {

    	private $_tBuilding;
        
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

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
		return trim( iconv( 'ISO-8859-1', 'UTF-8', $val) );
	}

}
?>
