<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file entity.class.php
 * @ingroup plurioparser
 */

class Entity extends WikiApiClient {

	protected static $_inGuide;	// one-dimensional
	protected static $_locIds;	// associative
	

	// Create two static arrays if they don't yet exist
	public function __construct(){
		parent::__construct();
		if( !isset( self::$_inGuide ) )
			self::$_inGuide = array();
		if( !isset( self::$_locIds ) )
			self::$_locIds = array();
	}

	/**
	 * Retrieves an ID from the list, or,
	 * if this is the first time we refer to this entitiy,
	 * from the wiki via the API
	 * (_getEntityIdFor() is defined in the respective child-classes)
	 */
	protected function getIdFor( $entity ) {
		if( !array_key_exists( $entity, self::$_locIds ) )
			self::$_locIds[$entity] = $this->_getEntityIdFor( $entity );
		return self::$_locIds[$entity];
	}

	protected function inGuide( $entity ) {
		if( in_array( $entity, self::$_inGuide ) )
			return true;
	}

	protected function addCategories( &$parent, array $cats ) {
		$categories = $parent->addChild('guideCategories');
 		foreach( $cats as $cat )
 			$categories->addChild('guideCategoryId', $cat );
 	}

}
