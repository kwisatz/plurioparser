<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file entity.class.php
 * @ingroup plurioparser
 */

//class Entity extends WikiApiClient {
class Entity {

	protected static $_inGuide;	// one-dimensional list of entities already in the guide
	protected static $_locIds;	// associative array of location ids and their labels
	protected $_source;		// points to source object
	

	// Create two static arrays if they don't yet exist
	public function __construct(){
		global $config;

		if( !isset( self::$_inGuide ) )
			self::$_inGuide = array();
		if( !isset( self::$_locIds ) )
			self::$_locIds = array();

		$this->_source = $this->_sourceFactory( $config['data.format'] );
	}


	/**
	 * Factory for source objects allowing us to retrieve additional information
	 * from the respective source as needed
	 */
	private function _sourceFactory( $source ) {
		switch( $source ) {
		case 'smw':
			return new WikiApiClient;
		break;
		case 'pdo':
			return new PDOMapper;
		break;
		default:
			throw new Exception( sprintf( 'No adapter for source format "%s"', $source ), 404 );
		break;
		}
	}

	/**
	 * Pass any queries on to the respective source
	 */
	public function __call( $method, $args ) {
		$args[] = $this;
		return call_user_func_array( array( $this->_source, $method), $args );
	}


	/**
	 * Retrieves an ID from the list, or,
	 * if this is the first time we refer to this entitiy,
	 * from the wiki via the API or the database
	 * (_getEntityIdFor() is defined in the respective child-classes)
	 */
	/*
	 * Shouldn't cache this here, since we don't even know what type the id will be off
	protected function _getIdFor( $entity ) {
		if( !array_key_exists( $entity, self::$_locIds ) )
			self::$_locIds[$entity] = $this->_source->getIdFor( $entity );
		return self::$_locIds[$entity];
	}
	 */

	/**
	 * Called from event.class
	 * Returns true if an entity is already in the guide and false if not
	 * @var $entity label of an entity, key to $_inGuide array
	 * @return bool
	 */
	protected function _inGuide( $entity ) {
		return in_array( get_class( $this ) . '_' . $entity, self::$_inGuide );
	}

	/**
	 * Adds categories to ???
	 * @var $parent the parent object
	 * @var $cats an array of categories to be added
	 * @return void
	 */
	protected function _addCategories( &$parent, array $cats ) {
		$categories = $parent->addChild('guideCategories');
 		foreach( $cats as $cat )
 			$categories->addChild('guideCategoryId', $cat );
 	}

}
