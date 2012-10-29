<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file building.class.php
 * @ingroup plurioparser
 */

class Building extends Entity {
	
	private $_buildingId = '225269';		// plurio ID for hackerspace
	private $_building;				// building xml object
	
	public function __construct(){
		parent::__construct();
	}

	/**
	 * Get the wiki's internal page id and use it as an extId
	 * Just a wrapper for _fetchPageId() that adds a prefix
	 * ATTENTION: Never call this directly from this class.
	 * There is just no way to verify the caller (other than debug_backtrace())
	 */
	protected function _getEntityIdFor( $location ) {
		return 'loc' . $this->_fetchPageId( $location );
	}
		
	
	/**
	 * Check that this is a valid buildings element
	 * then create the local xml representation
	 */
	private function _addTo( $buildings ) {
		if( !($buildings instanceOf SimpleXMLElement) )
			throw new Exception('No valid buildingsGuide element passed to ' . __METHOD__ );
		$this->_building = $buildings->addChild('entityBuilding');
	}


	/**
	 * This method called from event.class.php
	 * @param buildings The buildings sub-section in the guide section
	 * @param location The location ID 
	 * @param organisation The organisation ID
	 * @return locationId An internal ID for a location
	 * Entry point into building.class, adds the xml to the buildings section
	 * and then creates and adds a building section for the guide
	 * Returns the location id for use in event.class.php
	 */
	public function addToGuide( $buildings, $location, $organisation ){
		try {
			
			// create the building (and add the organisation as a relation)
			$this->_addTo( $buildings );
			$this->_create( $location, $organisation );
		
			// add it to the internal list and return the location ID 
			self::$_inGuide[] = get_class( $this ) . '_' . $location;
			return $this->getIdFor( $location );
		} catch ( Exception $e ) {
			if( $e->getCode() == 501 ) {
				return NULL;
			} else throw $e;
		}
	}
	
	/**
	 * Create a new building xml object, then return it to addToGuide()
	 * 
	 */
	private function _create( $identifier, $organisation ) {
		global $config;

		// fetch information about this location from its respective data source
		$info = $this->fetchLocationInfo( $identifier );
		$name = $info->label; 

		// we cannot add buildings that have no LocalisationId
		if( !$info->has_zipcode || !$info->has_city )
			throw new Exception( 'Cannot add this building, no zipcode or city supplied', 501 );

		$this->_building->addChild('name', $name );

		// FIXME!!!! FIXME FIXME FIXME
		// ok... but there aren't really any descriptions ... yet :/
		if( $info->label == "Hackerspace, Strassen" ){
			// we don't know if the building exists, and if it does, we 
			// would need to fetch the id from the plurio website
			// FIXME
			$this->_building->addAttribute('id', $this->_buildingId);

			$desc = new Descriptions( $this->_building );
			$desc->setShortDescription( 'en', 'syn2cat is a 120 sqm paradise for nerds, geeks and those who\'d fancy becoming one.' );
			$desc->setShortDescription( 'de', 'Der syn2cat Hackerspace ist ein 120m² großes Paradies für Geeks und Nerds' );
			$desc->setShortDescription( 'fr', 'Le hackerspace de syn2cat est un espace ouvert de 120 mètres carrés pour bidouilleurs.' );
		
			$desc->setLongDescription( 'en', "Our friendly environment enables you to work on your own or community projects. "
				."We have all the tools you'd ever require and will even buy "
				."those you don't. Produce your own objects with our Makerbot "
				."(3D printer), flash your microcontrollers with our µC "
				."programmers, pilot a quadrokopter, etch your own circuit "
				."boards, use our library, or become a member of the team "
				."that produces the Lët'z Hack radio show. There's still more"
				." services, and infrastructure the space puts at your "
				."disposal and by becoming a member, you support a unique "
				."infrastructure in all of Luxembourg."
			);
			
			// visitor Info (optional)FIXME FIXME FIXME
			$this->_building->addChild('visitorInfo','Please refer to our webpage to find out whether we\'re open!');
		}
			
		// address 
		$address = new Address;
		$address->number = $info->has_number;
		$address->street = $info->has_address;
		$address->zipcode = $info->has_zipcode;
		$address->city = $info->has_city;
		$address->country = $info->has_country;
		
		$address->venue = $info->label;
		$address->addTo( $this->_building );
		
		// prices
		$this->_building->addChild('prices')->addAttribute('freeOfCharge','true');

		// contactInformation 
		// ContactBuilding is optional. Thus if no information is available, don't even
		// create such a section
		if ( !( empty( $info->url ) && empty( $info->has_phonenumber ) && empty( $info->has_email_address ) ) ) {
			$contact = new Contact;
			$contact->setWebsiteUrl( $info->url );
			$contact->setPhoneNumber( $info->has_phonenumber );
			$contact->setEmailAddress( $info->has_email_address );
			$contact->addTo( $this->_building, $this );
		}
		
		// relationsBuilding >> organisation to building
		$relations = $this->_building->addChild('relationsBuilding');
		
		try {
			$orga = new Organisation;
			$orgaId = $orga->getIdFor( $organisation );
			$otb = $relations->addChild('organisationsToBuildings')->addChild('organisationToBuilding');
			$otb->addChild('extId', $orgaId);
			$otb->addChild('organisationRelBuildingTypeId','ob10');
		} catch (Exception $e) {
			if ($e->getCode() == 001) {
				if( $config['debug'] ) printf( "Skipped adding organisation to building since no organisation data available\n" );
			} else throw $e;
		}
		
		// relations >> building picture if there is one.
		if( isset($info->has_picture[0] ) ) {
			$pictures = $relations->addChild('pictures');
			$picture = new Picture;
			$picture->name = $info->has_picture[0];
			$picture->label = 'Illustration of ' . $name;
			$picture->position = 'default';
			$picture->addTo( $pictures );
		}
		
		// Mark all buildings that are not the Hackerspace as 
		// "Temporäre Veranstaltungsorte" (41)
		if( $info->label == "Hackerspace, Strassen" ){
			// FIXME: oops.. this should NOT be hardcoded
			//$this->_addCategories( $relations, array( 15, 213, 616, 617 ) );
			$this->_addCategories( $relations, array( 213, 344, 561, 616, 617 ) );
		} else {
			$this->_addCategories( $relations, array( 41 ) );
		}
	
		// Set user specific
		$us = $this->_building->addChild('userspecific');
		$locId = $this->getIdFor( $identifier );
		$us->addChild('entityId',$locId);
		$us->addChild('entityInfo','Building id '.$locId);	
		
	}
		
}

?>
