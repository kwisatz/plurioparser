<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file organisation.class.php
 * @ingroup plurioparser
 */

class Organisation extends Entity {
	
	private $_name;
	protected $_orgXml;	// internal xml object reference
	private $_sdescs;
	private $_ldescs;
	private $_orgaToBuildings;
	
	public function __construct(  ){
		parent::__construct();
	}
	
	/**
	 * Only now do we create the xml node 
	 */
	public function addTo( &$orgs ){
		if( !($orgs instanceOf SimpleXMLElement) )
			throw new Exception('No valid organisationsGuide element passed to ' . __METHOD__ );
		$this->_orgXml = $orgs->addChild('entityOrganisation');		
	}
	
	/**
	 * This is an organisation factory
	 */
	public function addToGuide( &$orgs, $organisation ){
		$this->addTo( $orgs );
		$info = $this->_fetchInformation( $organisation );
		
		$this->setName( substr( $organisation, 
			strpos( $organisation, ':' ) + 1 )
		);

		// Add descriptions
		$desc = new Descriptions( $this->_orgXml );
		if( $info->has_subtitle[0] )
			$desc->setShortDescription( 'en', $info->has_subtitle[0] );
		$desc->setLongDescription( 'en', $info->has_description[0] );
		
		// retrieve location information and add it as an address
		$location = $this->_fetchLocationInfo( $info->has_location[0] );
		$this->setAddress( $location->label, 
			$location->has_number, 
			$location->has_address, 
			$location->has_zipcode,
			$location->has_city );
		
		// Add contact details
		$this->setContact();
		$relations = $this->_orgXml->addChild('relationsOrganisation');
		
		// Retrieve information for related location and tie it to this organisation
		$building = new Building;
		$this->tieToBuilding( $relations, $building->getIdFor( $info->has_location[0] ) );

		// Add organisation logo
		$this->addLogo( $relations, $info->has_picture[0] );
		
		// how to determine that for other organisations?
		if ( $organisation == 'Organisation:Syn2cat' )
			$this->addCategories( $relations, array( 507, 510, 345, 616, 617 ) );
		
		// get the ID for this wiki entry and set it as user-specific id
		$orgId = $this->getIdFor( $organisation );
		$this->setUserSpecific( $orgId, $organisation . ' ID: ' . $orgId );	
			
		// finally add this organisation to the guide
		self::$_inGuide[] = $organisation;
		return $orgId;
	}
	
	private function _fetchInformation( $name ) {
		$query = 'http://wiki.hackerspace.lu/wiki/Special:Ask/'
			.'-5B-5B' . str_replace( ' ', '_', $name ) . '-5D-5D/'
			//.'-3FHas-20PageName/'
			.'-3FHas-20Contact/'
			.'-3FHas-20description/'
			.'-3FHas-20location/'
			.'-3FHas-20picture/'
			.'-3FHas-20subtitle/'
			.'-3FUrl/'
			.'format=json';
		$data = Parser::readJsonData( $query );
		return $data->items[0];
	}
	
	public function setOrgaId( $id ){
		if( !isset( $this->_orgXml->id ) )
			$this->_orgXml->addAttribute( 'id', $id );
		else throw new Exception('Trying to overwrite the organisation id.');
	}
	
	/**
	 * Just a wrapper for _fetchPageId() that adds a prefix
	 */
	protected function _getEntityIdFor( $organization ) {
		return 'org' . $this->_fetchPageId( $organization );
	}
	
	public function setName( $name ){
		$this->_orgXml->addChild( 'name', $name );
		$this->_name = $name;
	}

	/**
	 * We need to fetch this from a wiki page!!
	 */
	public function setAddress( $venue, $number, $street, $zipcode, $city ){
		$address = new Address;
		$address->number = $number;
		$address->street = $street;
		$address->zipcode = $zipcode;
		$address->city = $city;
		$address->venue = $venue;
		$address->addTo( $this->_orgXml );
	}

	public function setContact(){
		$contact = new Contact;
		$contact->addTo( $this->_orgXml, $this );
	}
	
	// organisations to Organisation (could add C3L here)
	
	public function tieToBuilding( $relations, $extId ){
		if(!isset($this->_orgaToBuildings) )
			$this->_orgaToBuildings = $relations->addChild('organisationsToBuildings');
			
		$otb = $this->_orgaToBuildings->addChild('organisationToBuilding');
		$otb->addChild('extId', $extId );
		$otb->addChild('organisationRelBuildingTypeId','ob10');
	}
	
	// organisation >> relations >> logo
	public function addLogo( $relations, $filename ) {
		$pictures = $relations->addChild('pictures');	// we don't need to store this, it's only one image
		
		$picture = new Picture;
		$picture->label = $this->_name . 'logo';
		$picture->position = 'default';
		$picture->name = $filename;
		$picture->addTo( $pictures );
	}
	
	// CHANGE!!! use variable data
	public function setUserSpecific( $extId, $info ){
		// userspecific
		$us = $this->_orgXml->addChild('userspecific');
		$us->addChild('entityId', $extId );
		$us->addChild('entityInfo', $info);
	}
	
}
