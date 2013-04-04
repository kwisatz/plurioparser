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
	
	private $_buildingId;		// plurio ID for mnhn
	private $_building;		// building xml object
	private static $_unusable;	// array of unusable locations
	
	public function __construct(){
            global $config;
            $this->_buildingId = $config['building.id'];
	    if( !isset( self::$_unusable ) ) {
		    self::$_unusable = array();
	    }
            parent::__construct();
	}		
	
	/**
	 * Check that this is a valid buildings element
	 * then create the local xml representation
	 */
	private function _addTo( $buildings ) {
		if( !($buildings instanceOf SimpleXMLElement) )
			throw new Exception('No valid buildingsGuide element passed to ' . __METHOD__ . "\n" );
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
	public function addToGuide( $buildings, $location, Array $eventData, $orig=false ){
		try {
                     	// fetch information about this location from its respective data source
                        $info = $this->fetchLocationInfo( $location );
                        
                        if( $location === "2" ) {	// MNHN hack, see exception catch for 501 below
				global $config;
				return array(
					'id' => $config['building.id'], 
					'info' => $orig
				);
			} else if ( !empty( $info->ID_plurio ) ) {	// FIXME: what about caching here?
				( $config['debug'] == "on" ) && printf('This location has a plurioID: %d. Not adding it to the guide.', $info->ID_plurio);
				return array(
					'id' => $info->ID_plurio, 
					'info' => false
				);
                        }
                        
                        // we cannot add buildings that have no LocalisationId
                    	if( !$info->has_zipcode || !$info->has_city || !$info->has_address ) {
				throw new Exception( 
					sprintf( 'Cannot add location (%s), no zipcode or city supplied', $info->label )
				       	. "\n", 501 );
                        }
                        
			// create the building (and add the organisation as a relation)
			$this->_addTo( $buildings );
			$this->_create( $location, $eventData['organisation'] );
		
			// add it to the internal list and return the location ID 
			self::$_inGuide[] = get_class( $this ) . '_' . $location;
			return $this->getIdFor( $location );
		} catch ( Exception $e ) {
			if( $e->getCode() == 900 ) {	// we're not catching 501 here, since nothing has been added to the object yet!
				unset($buildings->entityBuilding[sizeof($buildings->entityBuilding) - 1]); 
				throw $e;
			} else if ( $e->getCode() == 501 ) {
				// replace the location with the default location and add a string to the description
				// this is an MNHN workaround --> FIXME (2 is 'natur musee')
				if( !in_array( $location, self::$_unusable ) ) {
					printf( 'Unable to use location "%s" (id: %d, event: %s, in charge: %s) due to missing address information, using default.' . "\n\r", $info->label, $location, $eventData['label'], $eventData['in_charge'] );
					self::$_unusable[] = $location;
				}
				return $this->addToGuide( $buildings, "2", $eventData, $info->label );
			} else throw $e;
		}
	}
	
	/**
	 * Create a new building xml object, then return it to addToGuide()
	 * 
	 */
	private function _create( $identifier, $organisation ) {
		global $config;

                $info = $this->fetchLocationInfo( $identifier );
		$name = $info->label; 

		$this->_building->name = $name;
                
		/*
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
		 */
			
		// address 
		$address = new Address;
		$address->number = $info->has_number;
		$address->street = $info->has_address;
		$address->zipcode = $info->has_zipcode;
		$address->city = $info->has_city;
		$address->country = $info->has_country;
		
		$address->venue = $info->label;
		$address->addTo( $this->_building );

		// prices FIXME
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
			//$otb->addChild('extId', $orgaId);
                        // using plurio IDs for MNHN
                        $otb->addChild('id', $orgaId);
			$otb->addChild('organisationRelBuildingTypeId','ob10');
		} catch (Exception $e) {
			if ($e->getCode() == 001) {
				if( $config['debug'] ) printf( "Skipped adding organisation to building since no organisation data available.\n" );
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
		
		// Mark all buildings that are not the main building as
		// "Temporäre Veranstaltungsorte" (41)
		//if( $info->label == "Hackerspace, Strassen" ){
		if( $info->label == "'natur musée'" ){	// FIXME FIXME FIXME
			// FIXME: oops.. this should NOT be hardcoded
			//$this->_addCategories( $relations, array( 15, 213, 616, 617 ) ); 		// syn2cat
			//$this->_addCategories( $relations, array( 213, 344, 561, 616, 617 ) );	// 
			$this->_addCategories( $relations, 
				array( 
					14,	// museums->nature and science->man and nature
					31,	// museums->scientific classification->natural sciences
					82,	// hosts and organisers->exhibitions,visual arts->science, technology
					473,	// locations->young audiences
					476,	// locations->museums
					601,	// young audiences->museums
					608,	// leisure, traditions and others -> young audiences
					643	// leisure, traditions and others -> nature
				) 
			);
		} else {
			$this->_addCategories( $relations, array( 
				41 	//locations->temporary venues and locations
			) );
		}
	
		// Set user specific
		$us = $this->_building->addChild('userspecific');
		$locId = $this->getIdFor( $identifier );
		$us->addChild('entityId', 'mnhn' . $locId);
		$us->addChild('entityInfo','MNHN building id '.$locId);	
		
	}
		
}

?>
