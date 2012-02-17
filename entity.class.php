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
	
	protected $_relations;
	private $_categories;
	
	public function __construct(){
		parent::__construct();
	}

	public function addCategories( &$parent, array $cats ) {
		// Not sure whether this makes any sense.. this method is not
		// invoked more than once per entity anyway, or is it?
		if( !isset($this->_categories) )
 			$this->_categories = $parent->addChild('guideCategories');
 		
 		foreach( $cats as $cat )
 			$this->_categories->addChild('guideCategoryId', $cat );
 	}
	
	/**
	 * Hmm.. this is somewhat silly since we need the address multiple 
	 * times and should thus store it somewhere for every location.
	 * SWe should store it here in a static property of this entity class.
	 */
	protected function _fetchLocationInfo( $name ){
		$query = 'http://wiki.hackerspace.lu/wiki/Special:Ask/'
			.'-5B-5B' . str_replace( ' ', '_', $name ) . '-5D-5D/'
			.'-3FHas-20address/'
			.'-3FHas-20city/'
			.'-3FHas-20country/'
			.'-3FHas-20picture/'
			.'format=json';
		$data = Parser::readJsonData( $query );
		$info = $data->items[0];
		
		$ns =  explode(',', $info->has_address[0]);
		$zc = explode(',', $info->has_city[0]);
		
		if( sizeof($ns) > 1 ) {
			$info->has_number = trim( $ns[0] );
			$info->has_address = trim( $ns[1] );
		} else $info->has_address = $info->has_address[0];
		
		if( sizeof( $zc ) > 1 ) {
			$info->has_zipcode = trim( $zc[0] );
			$info->has_city = trim( $zc[1] );
		} else $info->has_city = $info->has_city[0];
		
		return $info;
	}

}
