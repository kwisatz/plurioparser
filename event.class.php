<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file event.class.php
 * @ingroup plurioparser
 */

class Event {
	
	private $_event;	// internal representation of event xml object
	private $_buildings;	// link to buildings node

	public function __construct( &$agenda, &$buildings ) {
			$this->_event = $agenda->addChild('event');
			$this->_buildings = $buildings;
	}
	
	private function _getDomain( $url ) {
		return = 'http://'.parse_url( $url, PHP_URL_HOST );		// duplicate in wikiapiclient
	}
	
	private function setDateTime( &$event, $startdate, $enddate ) {
		// date elements, need parsing first
		$startTime = strtotime($startdate);
		$endTime = strtotime($enddate);
		$dateFrom = date("Y-m-d",$startTime);
		$dateTo = date("Y-m-d",$endTime);
		$timingFrom = date("H:i",$startTime);
		$timingTo = date("H:i",$endTime);

		$date = $this->_event->addChild('date');
		$date->addChild('dateFrom',$dateFrom);
		$date->addChild('dateTo',$dateTo);
		$date->addChild('dateExclusions');

		$timing = $event->addChild('timings')->addChild('timing');
		$timing->addChild( 'timingDescription', 'Opening hours' );
		$timing->addChild( 'timingFrom', $timingFrom );
		$timing->addChild( 'timingTo', $timingTo );
	}
	
	private functin _setPrices( &$event, $cost ){
		// prices (if the price is 0 or something other than a numeric value, set freeOfCharge to true)
		$prices = $event->addChild('prices');
		$first = substr( $cost, 0, 1 );
		if ( (int) $cost == 0 ) {	// everything that does not evaluate to something sensible is 0
			$prices->addAttribute( 'freeOfCharge','true' );
		} else {
			$prices->addAttribute( 'freeOfCharge', 'false' );
			$price = $prices->addChild('price');
			$price->addChild('priceDescription','Fee');
			$price->addChild('priceValue',(int) $cost);
		}
	}
	
	public function createNewFromItem( $item ){
		$this->_event->addChild( 'name', $item->label );
		if($item->has_subtitle)
			$this->_event->addChild( 'subtitleOne', $item->has_subtitle[0] );
			
		$this->_event->addChild( 'localDescription', $item->has_location[0] );

		// XML Schema says short description must come before long description
		$shortDesc = $this->_event->addChild('shortDescriptions')->addChild('shortDescription');
		$shortDesc->addAttribute('autogenerate','true');
		$shortDesc->addAttribute('language','en');
		$longDesc = $this->_event->addChild('longDescriptions')->addChild('longDescription',$item->has_description[0]);
		$longDesc->addAttribute('language','en');

		// Add date and time
		$this->_setDateTime( $this->_event, $item->startdate[0], $item->enddate[0] );
		
		// Add prices
		$this->_setPrices( $this->_event, $item->has_cost[0] );

		// <contactEvent/>
		$contact = new Contact;
		$contact->add( $this->_event );
		// use external website if supplied, else wiki page
		$website = ( !empty( $item->Url[0] ) ) ? $item->Url[0] : $this->_getDomain( $item->bla ).'/wiki/'.str_replace(' ','_',$item->label)
		$contact->setWebsiteUrl( $website );
		// Or, if this IS an email address, use that, else use info@hackerspace.lu ?!
		//$contact->setEmailAddress($item->???)	// if it were possible to extract an email address from the "Has contact" property.
			
		// <relationsAgenda/>
		$relations = $this->_event->addChild('relationsAgenda');
		
		/**
		 * Our wiki semantics don't support internal events right now, so no <internalEvents/> here either
		 * But we do have multiple locations, so for each location, we need to check whether it already exists in the guide,
		 * and if not, add it
		 */
		$place = $relations->addChild('placeOfEvent');	// mandatory
		$place->addAttribute('isOrganizer','false');	// as directed by guideline
		
		$extId = $this->_addBuildingIfNotExists( $item->has_location[0] );
		$place->addChild('extId', $extId );

			// no <personsToEvent/>

			// <organisationsToEvent/>
			$orga = $relations->addChild('organisationsToEvent')->addChild('organisationToEvent');
			// child to this identical to placeOfEvent, see above
			// use our internal extId instead as requested by rh-dev
			//$orga->addChild('id',$this->_orgaId);
			$orga->addChild('extId',$this->_getOrganizationId( $item->has_organizer[0] ) );
			$orga->addChild('organisationRelEventTypeId','oe07');	// = organiser

			// agenda >> event >> relations >> pictures
			if( !empty($item->has_picture[0]) || !empty($item->has_highres_picture[0]) ) {
				$pictures = $relations->addChild('pictures');
				if( !empty( $item->has_picture[0] ) ) {
					$this->_addPicture( $pictures, $item->has_picture[0], 'default', $item->label);
				}
				if( !empty( $item->has_highres_picture[0] ) && $this->_isHighres( $item->has_highres_picture[0] ) ) {;
					$this->_addPicture( $pictures, $item->has_highres_picture[0], 'additional1', $item->label);
				}
			}

			// <agendaCategores/> - can have as many as we want
			$categories = $relations->addChild('agendaCategories');
			
			// map our categories to the corresponding plurio ones
			$mwtypes = ( is_array($item->is_type[0]) ) ? $item->is_type[0] : array($item->is_type[0]);
			$mwcats = ( is_array($item->category) ) ? $item->category : array($item->category);
			array_walk( $mwcats, 'self::_removeCategory' );
			$mwcats = array_unique(array_merge($mwtypes, $mwcats));
			foreach( $mwcats as $mwc ){
				if($mwc == 'RecurringEvent') continue;	// filter recurring event category
				foreach($this->_mapCategory($mwc) as $pcats)
					$categories->addChild('agendaCategoryId',$pcats);
			}

			// userspecific (unique ids)
			$us = $event->addChild('userspecific');
			$pid = 'ev' . $this->_fetchPageId($item->label);
			$us->addChild('entityId',$pid);
			$us->addChild('entityInfo','Hackespace event id '.$pid);
		}
	
	private function _addBuildingIfNotExists( $location ){
		$building = new Building;
		if( $building->inGuide( $location ) == false ){
			$extId = $building->addToGuide( $this->_buildings, $location, $organisation );
		else $extId = $building->getLocationId( $location );
		return $extId;
	}
	
	private function _removeCategory( &$value ) {
		$value = substr( $value, strpos( $value, ':') + 1);
	}
}
