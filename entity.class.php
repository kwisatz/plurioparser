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
	
	public function __construct(){
		parent::__construct();
	}

	public function addCategories( &$parent, array $cats ) {
		$categories = $parent->addChild('guideCategories');
 		foreach( $cats as $cat )
 			$categories->addChild('guideCategoryId', $cat );
 	}
	
	/**
	 * Hmm.. this is somewhat silly since we need the address multiple 
	 * times (1: Building, 2: Organisaton:58, ) 
	 * and should thus store it somewhere for every location.
	 * --> We should store it here in a static property of this entity class
	 * Or create one Building/Location object per Location and always retrieve
	 * information from there.
	 */
	protected function _fetchLocationInfo( $name ){
		$query = 'http://wiki.hackerspace.lu/wiki/Special:Ask/'
			.'-5B-5B' . str_replace( ' ', '_', $name ) . '-5D-5D/'
			.'-3FHas-20address/'
			.'-3FHas-20city/'
			.'-3FHas-20country/'
			.'-3FHas-20picture/'
			.'-3FUrl/'
			.'-3FHas-20email-20address/'
			.'-3FHas-20phonenumber/'
			.'format=json';
		$data = Parser::readJsonData( $query );
		$info = $data->items[0];
		
		// split street and country information
		$ns =  explode(',', $info->has_address[0]);
		$zc = explode(',', $info->has_city[0]);
		
		// account for locations that have no zipcode and/or housenumber
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
