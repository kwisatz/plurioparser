<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file wikiapiclient.class.php
 * @ingroup plurioparser
 */

namespace DataSource\MediaWiki;
use \Interface\DataSource as DSource;

class WikiApiClient implements DSource {
	
	protected static $_entityInfo;
	protected static $_apiData;
	
	public function __construct(){
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
		global $config;

		$qid = $title . '-' . implode( '-', $params );
		if( array_key_exists( $qid, self::$_apiData ) ) {
			if( $config['debug'] ) printf("Retrieved data for %s from cache. \o/\n", $title);
			return self::$_apiData[ $qid ];
		} else {
			if( $config['debug'] ) printf("Executing mw api query for %s\n", $title);
			$query = $config['mw.domain'] . '/' . $config['mw.filepath'] . '/api.php?action=query&titles=';
			$query .= str_replace(' ','_',$title);
			$query .= is_array( $params ) ? '&'.implode('&',$params) : '';
			$query .= '&format=json';
			$data = $this->_jsonDecode( $query );
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
	public function getIdFor( $title, $caller ) {
		$query = array( 'indexpageids' );
		$data = $this->_mwApiQuery( $title, $query );
		return $data->query->pageids[0];
	}

	/**
	 * SEMANTIC QUERIES
	 */

	/**
	 * Name as key is NOT a very good idea since the same with other parameters could be used
	 * FIXME: account for queries that set more than a single condition/name, i.e. Category:Event and StartDate::
	 * FIXME: allow for variable replacement
	 * FIXME: allow for <q>OR</q> queries, see initial smw source file
	 */
	private function _doSemanticQuery( $title, array $parameters ) {
		global $config;

		if( array_key_exists( $title, self::$_entityInfo ) ) {
			if( $config['debug'] ) printf("Retrieved data for %s from cache. \o/\n", $title);
			return self::$_entityInfo[$title];
		} else {
			if( $config['debug'] ) printf("Executing semantic query for location %s\n", $title);
			$query = $config['mw.domain'] . '/' . $config['mw.articlepath'] . '/Special:Ask/'
				. rawurlencode( '[[' ) 
				. str_replace( ' ', '_', $title ) 
				. rawurlencode( ']]' ) . '/';
			foreach( $parameters as $param ) {
				$query .= rawurlencode( '?' . $param ) . '/';
			}
			$query .= 'format=json';
			$query = str_replace('%','-',$query);		// don't ask
			$data = $this->_jsonDecode( $query );
			if ( $data ) {
				// add to the local cache (this saves a reference, not a copy!!)
				self::$_entityInfo[$title] = $data->items[0];
				return $data->items[0];
			} else throw new Exception('Could not execute your semantic query: ' . $query );
		}
	}

	public function fetchPictureInfo( $title ) {
		$query = array('prop=imageinfo','iiprop=url');
		$data = $this->_mwApiQuery( $title, $query);
		$keys = array_keys(get_object_vars($data->query->pages));
		$property = $keys[0];
		$url = $data->query->pages->$property->imageinfo[0]->url;
		return $url;
	}

	/**
	 * Retrieve information on an organisation using the Semantic Query */
	public function fetchOrganisationInfo( $name ) {
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
	public function fetchLocationInfo( $name ){
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

	/**
	 * Uses URL from intial source file, since that's a rather complexe
	 * query
	 */
	public function getInitialData( $input ) {
		$data = $this->_jsonDecode( $input );
		foreach( $data->items as &$item ) {
			$this->_parseDate( $item->startdate[0] );
			$this->_parseDate( $item->enddate[0] );
		}
		return $data;
	}

	/**
	 * Parse date elements and return the format we need for plurio.net
	 */
	private function _parseDate( &$datetime ) {
		$timestring = strtotime( $datetime );
		$date = date( "Y-m-d", $timestring );
		$time = date( "H:i", $timestring );

		$datetime = array( $date, $time );
	}

	/**
	 * Retrieve, parse, tidy
	 * and then return the json string
	 *
	 */
	private function _jsonDecode( $input ) {
		return json_decode( str_replace( array( "\n", "\t" ), '', file_get_contents( $input ) ) );
	}

}
