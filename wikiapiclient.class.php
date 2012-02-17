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
	
	public function __construct(){
		$this->_domain = 'http://wiki.hackerspace.lu';
	}
		
	/**
	 * Create a mediawiki api query
	 */
	protected function _mwApiQuery( $title, $params = NULL ) {
		$query = $this->_domain . '/w/api.php?action=query&titles=';
		$query .= str_replace(' ','_',$title);
		$query .= is_array( $params ) ? '&'.implode('&',$params) : '';
		$query .= '&format=json';
		$data = Parser::readJsonData( $query );
		if ($data) return $data;
		else throw new Exception('Could not query mediawiki API');
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
	
}
