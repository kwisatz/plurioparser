<?php

namespace PlurioParser\DataSource\PDO;

class Event {
	/**
	 * This is where we're doing the actual mapping to match the smw object
	 */

    	private $_tEvent;
        
	private $_pandaSignUp = 'http://www.panda-club.lu/umeldung/login/';
	private $_pandaMail = 'panda-club@mnhn.lu';
	private $_scienceSignUp = 'http://www.science-club.lu/umeldung/login/';
	private $_scienceMail = 'science-club@mnhn.lu';

	//FIXME: this mapping would be much better off in the config file!!
	public function __construct() {
		// setting data as first element of an array is necessary since the mediawiki
		// json export has these things exported as arrays as well. Both need to have the same structure
		// and since we're already mapping these to smw, we're changing everything here and nothing there
		!empty( $this->nom ) && $this->label = $this->_ic( $this->nom );

		!empty( $this->DateDebut ) && $this->startdate[0] = $this->_createDateArray( $this->_ic( $this->DateDebut ), $this->_ic( $this->Heure ) );
		!empty( $this->DateFin ) && $this->enddate[0] = $this->_createDateArray( $this->_ic( $this->DateFin ), $this->_ic( $this->Heure2 ) );

		!empty( $this->Description ) && $this->has_description[0] = $this->_ic( $this->Description );

		for( $i = 1; $i < 4; $i++ ) {
			$val = 'cat' . $i;
			!empty( $this->$val ) && $this->category[] = $this->_ic( $this->$val );
		}

		// data relative to the category
		if( !empty( $this->Categorie ) ) {
			$this->category[] = $this->_ic( $this->Categorie );
			$this->has_ticket_url[0] = ( $this->Categorie == 'Panda-Club' ) ? $this->_pandaSignUp : $this->_scienceSignUp;
			$this->has_contact[0] = ( $this->Categorie == 'Panda-Club' ) ? $this->_pandaMail : $this->_scienceMail;
		}

		!empty( $this->TrancheAge ) && $this->is_event_of_type[0] = $this->_ic( $this->TrancheAge );
		!empty( $this->IDlieu ) && $this->has_location_id[0] = $this->_ic( $this->IDlieu );
		!empty( $this->Lieu ) && $this->has_location[0] = $this->_ic( $this->Lieu );
		!empty( $this->Organisateur ) && $this->has_organizer[0] = $this->_ic( $this->Organisateur );
		!empty( $this->Prix ) && $this->has_cost = $this->_ic( $this->Prix );

		// FIXME: this is not compatible with SMW!! FIXME FIXME
		// either change the wikiapiclient class to do this too or remove this and let the Picture class do the work!
		!empty( $this->Image ) && $this->has_picture[0] = $this->_setHQPicture( $this->Image );
	}


	/**
	 * Using convert() might be an option, but it seems that using php conversion will be easier
	 * http://www.mssqltips.com/sqlservertip/1145/date-and-time-conversions-using-sql-server/
	 * See also wikiApiClient.class.php --> _parseDate() method
	 */
	private function _createDateArray( $date, $time ) {
		$datestring = strtotime( $date );
		$date = date( "Y-m-d", $datestring );

		$timestring = strtotime( $time );
		$time = date( "H:i", $timestring );
		$datetime = array( $date, $time );
		return $datetime;
	}

	/**
	 * Map SQLServer Latin_1 collation to Unicode
	 */
	private function _ic( $val ){
		return iconv( 'ISO-8859-1', 'UTF-8', $val);
	}

	private function _setHQPicture( $name ) {
		return substr($name, 0, -4) . 'HQ.jpg';
	}

}

?>
