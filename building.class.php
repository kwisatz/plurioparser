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
	
	private $_buildingId = '225269';		// plurio IDs
	private $_building;						// building xml object
	
	private $_sdescs;
	private $_ldescs;
	
	private static $_inGuide;				// list of buildings in guide
	
	public function __construct(){
		parent::__construct();
			
		if(empty( self::$_inGuide ) )
			self::$_inGuide = array();
	}
	
	/**
	 * We could easily return the id here
	 */
	public function inGuide( $location ){
		if( array_key_exists( $location, self::$_inGuide ) )
			return true;
	}
	
	public function addToGuide( &$buildings, $location, $organisation ){
		try {
			$this->_buildings = $buildings;
		
			// create the building (and add the organisation as a relation)
			$this->_create( $location, $organisation );
		
			// add it to the internal list
			$locId = $this->_getLocationIdFor( $location );
			self::$_inGuide[$location] = $locId;
			return $locId;
		} catch (Exception $e) {
			throw $e;
		}
	}
	
	public function getIdFor( $name ){
		// first check if we don't already have that id
		if( in_array( $name, self::$_inGuide ) ) {
			return self::$_inGuide[$name];
		} else { // else look it up through the wiki api
			return $this->_getLocationIdFor( $name );
		}
	}
	
	/**
	 * Just a wrapper for _fetchPageId() that adds a prefix
	 * This get's the wiki-internal page id and we use it as an extId
	 */
	private function _getLocationIdFor( $location ) {
		return 'loc' . $this->_fetchPageId( $location );
	}
		
	/**
	 * Create a new building xml object
	 * TODO: We should actually pull this from mediawiki
	 */
	private function _create( $name, $organisation ) {
		$this->_building = $this->_buildings->addChild('entityBuilding');
		// we don't know if the building exists, and if it does, we 
		// would need to fetch the id from the plurio website
		//$this->_building->addAttribute('id', $this->_buildingId);
		$this->_building->addChild('name', $name );
		
		$info = $this->_fetchLocationInfo( $name );
		// we cannot add buildings that have no LocalisationId
		if( !$info->has_zipcode || !$info->has_city ) return false;
			
		// Ahh.. this is not ok... we want to be able to set descriptions for any building actually
		// ok... but there aren't really any descriptions ... :/
		if( $info->label == "Hackerspace, Strassen" ){
			$this->_setShortDescription( 'en', 'auto' );
			$this->_setShortDescription( 'de', 'Der syn2cat Hackerspace ist ein 120m² großes Paradies für Geeks und Nerds' );
			$this->_setShortDescription( 'fr', 'Le hackerspace de syn2cat est un espace ouvert de 120 mètres carrés pour bidouilleurs.' );
		
			$this->_setLongDescription( 'en', "Our friendly environment enables you to work on your own or community projects. "
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
			
			// visitor Info (optional)
			$this->_building->addChild('visitorInfo','Please refer to our webpage to find out whether we\'re open!');
		}
			
		// address (this is silly, we already fetched that information)
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
		
		// contactInformation (hmm.. we don't store that)
		$contact = new Contact;
		//$contact->setWebsiteUrl();
		//$contact->setPhoneNumber();
		//$contact->setEmailAddress();
		$contact->addTo( $this->_building, $this );
		
		// relationsBuilding >> organisation to building
		$relations = $this->_building->addChild('relationsBuilding');
		$otb = $relations->addChild('organisationsToBuildings')->addChild('organisationToBuilding');
		$orga = new Organisation;
		$otb->addChild('extId', $orga->getIdFor( $organisation ) );
		$otb->addChild('organisationRelBuildingTypeId','ob10');
		
		// relations >> building picture if there is one.
		if( isset($info->has_picture[0] ) ) {
			$pictures = $relations->addChild('pictures');
			$picture = new Picture;
			$picture->name = $info->has_picture[0];
			$picture->label = 'Illustration of ' . $name;
			$picture->position = 'default';
			$picture->addTo( $pictures );
		}
		
		$this->addCategories( $relations, array( 15, 213, 616, 617 ) );
	
		$us = $this->_building->addChild('userspecific');
		$locId = $this->_getLocationIdFor( $name );
		$us->addChild('entityId',$locId);
		$us->addChild('entityInfo','syn2cat location '.$locId);	
		
	}
	
	// descriptions	( We should definitely try to fetch them from somewhere)
	private function _setShortDescription( $lang, $desc ){
		if(!isset( $this->_sdescs ))
			$this->_sdescs = $this->_building->addChild('shortDescriptions');
				
		if( $desc === 'auto' ) {
			$tdesc = $this->_sdescs->addChild('shortDescription');	
			$tdesc->addAttribute('autogenerate','true');
		} else {
			$tdesc = $this->_sdescs->addChild('shortDescription', $desc);	
			$tdesc->addAttribute('autogenerate','false');
		}
		$tdesc->addAttribute('language', $lang );
	}
	
	private function _setLongDescription( $lang, $desc ) {
		if(!isset( $this->_ldescs ))
			$this->_ldescs = $this->_building->addChild('longDescriptions');
		
		$lde = $this->_ldescs->addChild('longDescription', $desc );
		$lde->addAttribute('language', $lang );
	}
		
}

?>
