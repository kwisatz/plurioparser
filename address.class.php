<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file address.class.php
 * @ingroup plurioparser
 */

class Address {
	
	private $_localisationIdFile = 'localisationIDs_Luxembourg.xml';	// File that keeps localisation ids
	private static $_localisation_ids;
	
	private $_values;			// Store values for an address
	private static $_calls;

	public function __construct( ){
		if( !isset( self::$_localisation_ids ) )
			self::$_localisation_ids = simplexml_load_file( $this->_localisationIdFile );
		// Call calls to class for debug
		self::$_calls++;
	}
	
	public function __set( $name, $value ){
		$this->_values[$name] = $value;
	}
	
	/**
	 * Values for this are looked up in the wiki and that is being
	 * done in the Building->Entity->WikiApiClient class.
	 */
	public function addTo( &$entity ) {
		$address = $entity->addChild('adress');	// (sic)
		$address->addChild('street',$this->_values['street']);
		$address->addChild('houseNumber',$this->_values['number']);
		$address->addChild('placing', $this->_values['venue']);
		$address->addChild('poBox', $this->_values['zipcode']);
		
		// Fetch the LocalisationId from the XML file supplied by plurio
		$address->addChild( 'localisationId', $this->_fetchLocalisationId( $this->_values['city'], $this->_values['zipcode'] ));	
	}
	
	/**
	 * Look up this location's localisation ID from the plurio file
	 * Only supports Luxembourg at this moment
	 */
	private function _fetchLocalisationId( $city, $zipcode ) {
		$zipcode = ( substr($zipcode, 0, 1) == 'L' ) ? $zipcode : 'L-' . $zipcode;
					
		foreach( self::$_localisation_ids as $localisation ) {
			if( strtolower( $localisation->city ) == strtolower( $city )
			&& $localisation->zipcode == $zipcode )
				return $localisation['id'];
		}
	}

}
