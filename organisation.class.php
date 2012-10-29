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
	protected $_organisation;	// internal xml object reference
	private $_orgaToBuildings;
	
	public function __construct(  ){
		parent::__construct();
	}
	
	/**
	 * Get the wiki's internal page id and use it as an extId
	 * Just a wrapper for _fetchPageId() that adds a prefix
	 */
	/*protected function _getEntityIdFor( $organization ) {
		return 'org' . $this->_fetchPageId( $organization );
	}*/
	
	/**
	 * Only now do we create the xml node 
	 */
	private function _addTo( $orgs ){
		if( !($orgs instanceOf SimpleXMLElement) )
			throw new Exception('No valid organisationsGuide element passed to ' . __METHOD__ );
		$this->_organisation = $orgs->addChild('entityOrganisation');		
	}
	
	/**
	 * This is an organisation factory
	 */
	public function addToGuide( $orgs, $organisation ){
		try {
			$this->_addTo( $orgs );
			$this->_create( $organisation );

			// finally add this organisation to the guide
			self::$_inGuide[] = get_class( $this ) . '_' . $organisation;
			return $this->getIdFor( $organisation );

		} catch (Exception $e) {
			if( $e->getCode() == '001' ) {
				print("Oops, no organisation info to be fetched.\n");
				return false;
			} else throw $e;
		}
	}

	private function _create( $organisation ) {
		try {
			$info = $this->fetchOrganisationInfo( $organisation );
		} catch (Exception $e) {
			print($e->getMessage());
			return false;
		}

		$this->setName( substr( $organisation, 
			strpos( $organisation, ':' ) + 1 )
		);

		// Add descriptions
		$desc = new Descriptions( $this->_organisation );
		if( $info->has_subtitle[0] )
			$desc->setShortDescription( 'en', $info->has_subtitle[0] );
		$desc->setLongDescription( 'en', $info->has_description[0] );
		
		// retrieve location information and add it as an address
		$location = $this->fetchLocationInfo( $info->has_location[0] );
		$this->setAddress( $location->label, 
			$location->has_number, 
			$location->has_address, 
			$location->has_zipcode,
			$location->has_city );
		
		// Add contact details
		$this->setContact();
		$relations = $this->_organisation->addChild('relationsOrganisation');
		
		// Retrieve information for related location and tie it to this organisation
		$building = new Building;
		$this->tieToBuilding( $relations, $building->getIdFor( $info->has_location[0] ) );

		// Add organisation logo
		$this->addLogo( $relations, $info->has_picture[0] );
		
		// FIXME: how to determine that for other organisations? FIXME FIXME
		if ( $organisation == 'Organisation:Syn2cat' )
			$this->_addCategories( $relations, array( 507, 510, 345, 616, 617 ) );
		
		// get the ID for this wiki entry and set it as user-specific id
		$orgId = $this->getIdFor( $organisation );
		$this->setUserSpecific( $orgId, 'Hackerspace organisation id ' . $orgId );	
			
		return $orgId;
	}
	
	public function setOrgaId( $id ){
		if( !isset( $this->_organisation->id ) )
			$this->_organisation->addAttribute( 'id', $id );
		else throw new Exception('Trying to overwrite the organisation id.');
	}
	
	public function setName( $name ){
		$this->_organisation->addChild( 'name', $name );
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
		$address->addTo( $this->_organisation );
	}

	public function setContact(){
		$contact = new Contact;
		$contact->addTo( $this->_organisation, $this );
	}
	
	// organisations to Organisation (could add C3L here)
	
	// organisation to building (Currently disabled, see issue #1188)
	public function tieToBuilding( $relations, $extId ){
		return true;
		if(!isset($this->_orgaToBuildings) )
			$this->_orgaToBuildings = $relations->addChild('organisationsToBuildings');
			
		$otb = $this->_orgaToBuildings->addChild('organisationToBuilding');
		$otb->addChild('extId', $extId );
		$otb->addChild('organisationRelBuildingTypeId','ob10');	// FIXME HARDCODED!!
	}
	
	// organisation >> relations >> logo
	public function addLogo( $relations, $filename ) {
		$pictures = $relations->addChild('pictures');	// we don't need to store this, it's only one image
		
		$picture = new Picture;
		$picture->label = $this->_name . ' logo';
		$picture->position = 'default';
		$picture->name = $filename;
		$picture->addTo( $pictures );
	}
	
	public function setUserSpecific( $extId, $info ){
		// userspecific
		$us = $this->_organisation->addChild('userspecific');
		$us->addChild('entityId', $extId );
		$us->addChild('entityInfo', $info);
	}
	
}
