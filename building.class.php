<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file building.class.php
 * @ingroup plurioparser
 */

class Building extends WikiApiClient {
	
	private $_buildingId = '225269';		// plurio IDs
	private $_building;						// building xml object
	
	private $_sdescs;
	private $_ldescs;
	
	private static $_inGuide;				// list of buildings in guide
	
	public function __construct(){
		if(empty( self::$_inGuide ) )
			self::$_inGuide = array();
	}
	
	public function inGuide( $location ){
		if( in_array( $location, self::$_inGuide ) )
			return true;
	}
	
	public function addToGuide( &$buildings, $location, $organisation ){
		// create the item
		$this->_create();
		
		// add it to the internal list
		self::$_inGuide[] = $location;
	}
	
	public function getLocationId( $location ){
	}
	
	/**
	 * Only return xml when needed
	 */
	public function getXML(){
	}
		
	/**
	 * Create a new building xml object
	 * TODO: We should actually pull this from mediawiki
	 */
	public function create( $name, $organisation ) {
		$this->_building = $this->_buildings->addChild('entityBuilding');
		// we don't know if the building exists, and if it does, we 
		// would need to fetch the id from the plurio website
		//$this->_building->addAttribute('id', $this->_buildingId);
		$this->_building->addChild('name', $name );
		
		$info = $this->_fetchInformation( $name );
		
		// Ahh.. this is not ok... we want to be able to set descriptions for any building actually
		$this->_setShortDescription( 'en', 'auto' );
		$this->_setShortDescription( 'de', 'Das syn2cat Hackerspace ist ein 120m² großes Paradies für Geeks und Nerds' );
		$this->_setShortDescription( 'fr', 'Le hackerspace de syn2cat est un espace ouvert de 120 mètres quarrés pour bidouilleurs.' );
		
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
		
		// address
		$address = new Address;
		$address->number = $number;
		$address->street = $street;
		$address->zipcode = $zipcode;
		$address->city = $city;
		$address->venue = $venue;
		$address->addTo( $this->_building );
		
		// prices
		$this->_building->addChild('prices')->addAttribute('freeOfCharge','true');
		
		// contactInformation
		$contact = new Contact;
		$contact->setWebsiteUrl();
		$contact->setPhoneNumber();
		$contact->setEmailAddress();
		$contact->addTo( $this->_building );
		
		// relationsBuilding >> organisation to building
		$relations = $this->_building->addChild('relationsBuilding');
		$otb = $relations->addChild('organisationsToBuildings')->addChild('organisationToBuilding');
		$otb->addChild('id',$orga->getIdFor( $organisation ) );
		$otb->addChild('organisationRelBuildingTypeId','ob10');
		
		// relations >> building picture
		$pictures = $relations->addChild('pictures');
		$this->_addPicture( $pictures, $this->_bldLogo, 'default', 'the hackerspace');
	}



		// relations >>  building categories
		$gcats = $relations->addChild('guideCategories');
		$gcats->addChild('guideCategoryId','616');
		$gcats->addChild('guideCategoryId','617');
		$gcats->addChild('guideCategoryId','213');
		$gcats->addChild('guideCategoryId','15');

		$us = $building->addChild('userspecific');
		$locId = $this->_getLocationId('Hackerspace, Strassen');
		$us->addChild('entityId',$locId);
		$us->addChild('entityInfo','Hackerspace Building '.$locId);
	}
	
		// descriptions	( We should definitely try to fetch them from somewhere)
	private function _setShortDescription( $lang, $desc ){
		if(!isset( $this->_sdescs ))
			$this->_sdescs = $this->_building->addChild('shortDescriptions');
				
		if( $desc === 'auto' ) {
			$tdesc = $this->sdescs->addChild('shortDescription');	
			$tdesc->addAttribute('autogenerate','true');
		else {
			$tdesc = $this->sdescs->addChild('shortDescription', $desc);	
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
