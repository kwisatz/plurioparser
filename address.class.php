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
	
	//private $_plurio_localisation_ids = 'http://www.plurio.net/XML/listings/localisations.php';	// too slow
	private $_localisationIdFile = 'localisationIDs_Luxembourg.xml';	// File that keeps localisation ids
	private $_localisation_ids;
	
	private $_values;			// Store values for an address

	/**
	 * We need to fetch this from the wiki page!!
	 */
	public function __construct( ){
	}
	
	public function __set( $name, $value ){
		$this->_values[$name] = $value;
	}
	
	/**
	 * We need to fetch this from the wiki page!!
	 */
	public function addTo( &$entity ) {
		$address = $entity->addChild('adress');	// (sic)
		$address->addChild('street',$this->_values['street']);
		$address->addChild('houseNumber',$this->_values['number']);
		$address->addChild('placing', $this->_values['venue']);
		$address->addChild('poBox', $this->_values['zipcode']);
		
		// Fetch the LocalisationId from the XML File
		$address->addChild( 'localisationId', $this->_fetchLocalisationId( $this->_values['city'], $this->_values['zipcode'] ));	
	}
	
	/**
	 * Look up this location's localisation ID from the plurio file
	 * Only supports Luxembourg at this moment
	 */
	private function _fetchLocalisationId( $city, $zipcode ) {
		$zipcode = ( substr($zipcode, 0, 1) == 'L' ) ? $zipcode : 'L-' . $zipcode;
			
		foreach( $this->_localisation_ids as $localisation ) {
			if( strtolower( $localisation->city ) == $city
			&& $localisation->zipcode == $zipcode )
				return $localisation['id'];
		}
	}

}
