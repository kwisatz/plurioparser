<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file wikiapiclient.class.php
 * @ingroup plurioparser
 */

class WikiApiClient {
	
	protected $_domain;
	protected static $_entityInfo;
	protected static $_apiData;
	
	public function __construct(){
		$this->_domain = 'http://wiki.hackerspace.lu';
		if( !isset( self::$_entityInfo ) )
			self::$_entityInfo = array();
		if( !isset( self::$_apiData ) )
			self::$_apiData = array();
	}
		
	/**
	 * MW API QUERY
	 * Create a mediawiki api query
	 */
	protected function _mwApiQuery( $title, $params = NULL ) {
		$qid = $title . '-' . implode( '-', $params );
		if( array_key_exists( $qid, self::$_apiData ) ) {
			if( DEBUG ) printf("Retrieved data for %s from cache. \o/\n", $title);
			return self::$_apiData[ $qid ];
		} else {
			if( DEBUG ) printf("Executed mw api query for %s\n", $title);
			$query = $this->_domain . '/w/api.php?action=query&titles=';
			$query .= str_replace(' ','_',$title);
			$query .= is_array( $params ) ? '&'.implode('&',$params) : '';
			$query .= '&format=json';
			$data = Parser::readJsonData( $query );
			if ($data) {
				self::$_apiData[$qid] = $data;
				return $data;
			} else throw new Exception('Could not query mediawiki API');
		}
	}

	/**
	 * Uses _mwApiQuery()
	 * Fetch a page id from the wiki identified by its title
	 * @param $title The page's title
	 * @return mediawiki page id
	 */
	protected function _fetchPageId( $title ) {
		$query = array('indexpageids');
		$data = $this->_mwApiQuery( $title, $query );
		return $data->query->pageids[0];
	}

	/**
	 * SEMANTIC QUERIES
	 */

	/**
	 * Name as key is NOT a very good idea since the same with other parameters could be used
	 */
	private function _doSemanticQuery( $name, array $parameters ) {
		if( array_key_exists( $name, self::$_entityInfo ) ) {
			if( DEBUG ) printf("Retrieved data for %s from cache. \o/\n", $name);
			return self::$_entityInfo[$name];
		} else {
			if( DEBUG ) printf("Executing semantic query for location %s\n", $name);
			$query = 'http://wiki.hackerspace.lu/wiki/Special:Ask/'
				. rawurlencode( '[[' ) 
				. str_replace( ' ', '_', $name ) 
				. rawurlencode( ']]' ) . '/';
			foreach( $parameters as $param ) {
				$query .= rawurlencode( '?' . $param ) . '/';
			}
			$query .= 'format=json';
			$query = str_replace('%','-',$query);		// don't ask
			$data = Parser::readJsonData( $query );
			if ( $data ) {
				// add to the local cache (this saves a reference, not a copy!!)
				self::$_entityInfo[$name] = $data->items[0];
				return $data->items[0];
			} else throw new Exception('Could not execute your semantic query: ' . $query );
		}
	}

	/**
	 * Retrieve information on an organisation using the Semantic Query */
	protected function _fetchOrganisationInfo( $name ) {
		$info = $this->_doSemanticQuery( $name, 
			array(
				'Has Contact',
				'Has description',
				'Has location',
				'Has picture',
				'Has subtitle',
				'Url'
			)
		);
		return $info;
	}


	/**
	 * Retrieve information on a location using the Semantic Query
	 * 
	 */
	protected function _fetchLocationInfo( $name ){
		// work on a copy, not on the original object!
		$info = clone $this->_doSemanticQuery( $name,
			array(
				'Has address',
				'Has city',
				'Has country',
				'Has picture',
				'Url',
				'Has email address',
				'Has phonenumber'
			)
		);

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
