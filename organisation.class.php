<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file organisation.class.php
 * @ingroup plurioparser
 */

// http://xml.syyncplus.net/html/1_6/plurio_net_xml_schema_definition.html

class Organisation extends WikiApiClient {
	
	private $_orgXml;	// internal xml object reference
	private $_sdescs;
	private $_ldescs;
	private $_relations;
	private $_orgaToBuildings;
	private $_categories;
	
	private static $_orgs;
	
	public function __construct( &$orgs ){
		$this->_orgXml = $orgs->addChild('entityOrganisation');			
	}
	
	public function setOrgaId( $id ){
		if( !isset( $this->_orgXml->id ) )
			$this->_org->addAttribute( 'id', $id );
		else throw new Exception('Trying to overwrite the organisation id.');
	}
	
	public function getIdFor( $name ){
		// first check if we don't already have that id
		if( in_array( $name, self::_$orgs ) {
			return self::_$orgs[$name];
		} else { // else look it up through the wiki api
			return $this->_getOrganizationId( $name );
		}
	}
	
	public function setName( $name ){
		$this->_org->addChild( 'name', $name );
	}

	// descriptions	( We should definitely try to fetch them from somewhere)
	public function setShortDescription( $lang, $desc ){
		if(!isset( $this->_sdescs ))
			$this->_sdescs = $this->_orgXml->addChild('shortDescriptions');
				
		if( $desc === 'auto' ) {
			$tdesc = $this->sdescs->addChild('shortDescription');	
			$tdesc->addAttribute('autogenerate','true');
		else {
			$tdesc = $this->sdescs->addChild('shortDescription', $desc);	
			$tdesc->addAttribute('autogenerate','false');
		}
		$tdesc->addAttribute('language', $lang );
	}
	
	public function setLongDescription( $lang, $desc ) {
		if(!isset( $this->_ldescs ))
			$this->_ldescs = $this->_orgXml->addChild('longDescriptions');
		
		$lde = $this->_ldescs->addChild('longDescription', $desc );
		$lde->addAttribute('language', $lang );
	}
	
	/**
	 * We need to fetch this from a wiki page!!
	 */
	public function setAddress( $vanue, $number, $street, $zipcode, $city ){
		$address = new Address;
		$address->number = $number;
		$address->street = $street;
		$address->zipcode = $zipcode;
		$address->city = $city;
		$address->venue = $venue;
		$address->addTo( $this->_org );
	}

	public function setContact(){
		$contact = new Contact;
		$contact->set( $this->_org );
	}
	
	// organisations to Organisation (could add C3L here)
	
	private function _relationExists(){
		if(!isset($this->_relations) )
			$this->_relations = $this->_org->addChild('relationsOrganisation');
	}
	
	public function tieToBuilding( $extId ){
		$this->_relationExists();	// verify that the relations element exists
			
		if(!isset($this->_orgaToBuildings) )
			$this->_orgaToBuildings = $this->_relations->addChild('organisationsToBuildings');
			
		$otb = $this->_orgaToBuildings->addChild('organisationToBuilding');
		$bto->addChild('extId',$this->_buildingId);
		$bto->addChild('organisationRelBuildingTypeId','ob10');
	}
	
	// organisation >> relations >> syn2cat logo
	public function addLogo( $filename ) {
		$this->_relationExists();	// verify that the relations element exists
		$pictures = $this->_relations->addChild('pictures');	// we don't need to store this, it's only one image
		$picture = new Picture;
		$picture->add($pictures, $filename, 'default', 'syn2cat logo');
	}
	
	// overwrites entity::addCategories ??
	public function addCategories( array $cats ) {
		$this->_relationExists();	// verify that the relations element exists
 		if( !isset($this->_categories) )
 			$this->_categories = $this->_relations->addChild('guideCategories');
 		
 		foreach( $cats as $cat )
 			$this->_categories->addChild('guideCategoryId','507');
 	}

	public function setUserSpecific(){
		// userspecific
		$us = $this->_org->addChild('userspecific');
		$us->addChild('entityId',$this->_getOrganizationId( 'Organisation:Syn2cat' ) );
		$us->addChild('entityInfo','Hackerspace Organisation 01');
	}

	/**
	 * Just a wrapper for _fetchPageId() that adds a prefix
	 */
	private function _getOrganizationId( $organization ) {
		return 'org' . $this->_fetchPageId( $organization );
	}
	
}
