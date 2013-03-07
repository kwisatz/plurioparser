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
	
	private static $_localisation_ids;
	
	private $_values;			// Store values for an address
	private static $_calls;

	public function __construct( ){
		global $config;

		if( !isset( self::$_localisation_ids ) )
			self::$_localisation_ids = simplexml_load_file( $config['localisation.dst'] );
		// Sum calls to class for debug
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

		$address->street = $this->_values['street'];

		$address->addChild('houseNumber',$this->_values['number']);
		$address->placing = $this->_values['venue'];	
		
		$address->addChild('poBox', $this->_values['zipcode']);
		
		// Fetch the LocalisationId from the XML file supplied by plurio
		$lid = $this->_fetchLocalisationId( $this->_values['city'], $this->_values['zipcode'] );
		$address->addChild( 'localisationId', $lid );
	}
	
	/**
	 * Look up this location's localisation ID from the plurio file
	 */
	private function _fetchLocalisationId( $city, $zipcode ) {
		global $config;
					
		foreach( self::$_localisation_ids as $localisation ) {
			if( 
				strtolower( $localisation->city ) == strtolower( $city )
				&& ( 
					$localisation->zipcode == $zipcode 
					|| $localisation->zipcode == 'L-' . $zipcode
				)
			) {
				$config['debug'] && printf( "Region is %s\n", $localisation->region);
				return $localisation['id'];
			}
		}
		throw new Exception( 
			sprintf( "LocalisationID for city %s and zipcode %d not found!\n", $this->_values['city'], $this->_values['zipcode'] ),
			900 );
	}

}
