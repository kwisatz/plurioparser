<?php
/**
 * Parser that uses data from a semantic wiki and outputs an
 * XML file for import into plurio.net
 * 
 * @author David Raison <david@hackerspace.lu>
 * @file event.class.php
 * @ingroup plurioparser
 */

class Event extends Entity {
	
	private $_event;	// internal representation of event xml object
	private $_buildings;	// referrer to guide>>buildings node
	private $_orgs;		// referrer to guide>>organisations node
	
	private $_sdescs;	// rep of short descriptions
	private $_ldescs;	// rep of long descriptions
	
	/**
	 * Construct the event object and assign buildings and organisations
	 * guide sections
	 */
	public function __construct( &$agenda, &$buildings, &$orgs ) {
		parent::__construct();
		$this->_event = $agenda->addChild('event');
		$this->_buildings = $buildings;
		$this->_orgs = $orgs;
	}
	
	/**
	 * The idea is to use their xml file for mapping. 
	 * But how can we do that automatically?
	 */
	private function _mapCategory($mwc){
		$c = array();
		switch($mwc) {
			case 'Excursion':
			case 'Camp':
				$c[] = 442;	// leisure, excursions and hikes
			break;
			case 'Exhibiton':
				$c[] = 405;	// collections, science & technology
				$c[] = 398;	// collections, new media
			break;
			case 'Music':
				$c[] = 261;	// music, rock, hiphop, pop, electronic
			break;
			case 'Presentation':
			case 'Seminar':
			case 'Conference':
			case 'Congress':
			case 'Convention':
				$c[] = 427;	// living heritage, lectures, professional
			break;
			case 'Workshop':
			case 'Hackathon':
				$c[] = 426;	// living heritage, workshops
			break;
			case 'U19':
				$c[] = 465;	// young audiences, living heritage
			break;
			// fit all category
			case 'Meeting':
			case 'Event':
			case 'Party':
			default:
				$c[] = 433;	// living heritage, other
			break;
		}
		return $c;
	}

	
	/**
	 * Event factory
	 */
	public function createNewFromItem( $item ){
		$this->_event->addChild( 'name', $item->label );
		if($item->has_subtitle)
			$this->_event->addChild( 'subtitleOne', $item->has_subtitle[0] );
			
		$this->_event->addChild( 'localDescription', $item->has_location[0] );

		// XML Schema says short description must come before long description
		$desc = new Descriptions( $this->_event );
		if( $item->has_subtitle[0] )
			$desc->setShortDescription( 'en', $item->has_subtitle[0] );
		$desc->setLongDescription( 'en', $item->has_description[0] );

		// Add date and time
		$this->_setDateTime( $this->_event, $item->startdate[0], $item->enddate[0] );

		// Add prices
		$this->_setPrices( $this->_event, $item->has_cost[0] );

		// Add ticketing (if available)
		if( $item->has_ticket_url[0] ) {
			$this->_addTicketing( 
				$this->_event, 
				$item->has_ticket_url[0], 
				$item->startdate[0]
			);
		}
		
		// <contactEvent/>, create, add to this event and pass the current object 
		// in order to identify the type of contact to be added
		$contact = new Contact;

		// use external website if supplied, else wiki page
		$website = ( !empty( $item->url[0] ) ) ? $item->url[0] : $this->_domain
			. '/wiki/' . str_replace( ' ', '_', $item->label );
		$contact->setWebsiteUrl( $website );
		$contact->addTo( $this->_event, $this );

		// Or, if this IS an email address, use that, else use info@hackerspace.lu ?!
		//$contact->setEmailAddress($item->???)	// if it were possible to extract an email address from the "Has contact" property.
		// ATTENTION/FIXME: addContactEmail is currently called in the constructor.
			
		// <relationsAgenda/>
		$relations = $this->_event->addChild('relationsAgenda');
		
		// BUILDING
		/**
		 * Our wiki semantics don't support internal events right now, so no <internalEvents/> here either
		 * But we do have multiple locations, so for each location, we need to check whether it already exists in the guide,
		 * and if not, add it
		 */
		$place = $relations->addChild('placeOfEvent');	// mandatory
		$place->addAttribute('isOrganizer','false');	// as directed by guideline
		
		$building = new Building;
		if( !$building->inGuide( $item->has_location[0] ) ){
			$buildingExtId = $building->addToGuide( 
				$this->_buildings, 
				$item->has_location[0], 
				$item->has_organizer[0] );
		} else $buildingExtId = $building->getIdFor( $item->has_location[0] );
		$place->addChild('extId', $buildingExtId );

		// no <personsToEvent/>

		// ORGANISATION
		// <organisationsToEvent/>
		$orga = $relations->addChild('organisationsToEvent')->addChild('organisationToEvent');
		$organisation = new Organisation;
		if ( $organisation->inGuide( $item->has_organizer[0] ) ) {
			$organisationExtId = $organisation->getIdFor( $item->has_organizer[0] );
		} else {
			$organisationExtId = $organisation->addToGuide( $this->_orgs, $item->has_organizer[0] );
		}
			
		$orga->addChild('extId',$organisationExtId );
		$orga->addChild('organisationRelEventTypeId','oe07');	// = organiser

		// agenda >> event >> relations >> pictures
		if( !empty($item->has_picture[0]) || !empty($item->has_alternate_picture[0]) ) {
		$count = 0;
		$pictures = $relations->addChild('pictures');
		
			if( !empty( $item->has_picture[0] ) ) {
				$count++;	// we increase but don't use this here
				$picture = new Picture;
				$picture->name = $item->has_picture[0];
				$picture->position = 'default';
				$picture->label = $item->label;
				$picture->addTo( $pictures );
			}

			if( !empty( $item->has_alternate_picture[0] ) ) {
				$picture = new Picture;
				$picture->name = $item->has_alternate_picture[0];
				$picture->position = 'additional' . $count++;
				$picture->label = $item->label;
				$picture->addTo( $pictures );
			}
		}

		// <agendaCategores/> - can have as many as we want
		$categories = $relations->addChild('agendaCategories');
			
			// map our categories to the corresponding plurio ones
		$mwtypes = ( is_array($item->is_event_of_type[0]) ) 
			? $item->is_event_of_type[0] 
			: array($item->is_event_of_type[0]);
		$mwcats = ( is_array($item->category) ) ? $item->category : array($item->category);
		array_walk( $mwcats, 'self::_removeCategoryPrefix' );	
		$mwcats = array_unique(array_merge($mwtypes, $mwcats));
		foreach( $mwcats as $mwc ) {
			if($mwc == 'RecurringEvent') continue;	// filter recurring event category
			foreach($this->_mapCategory($mwc) as $pcats)
				$categories->addChild('agendaCategoryId',$pcats);
		}

		// userspecific (unique ids)
		$us = $this->_event->addChild('userspecific');
		$pid = 'ev' . $this->_fetchPageId( $item->label );
		$us->addChild('entityId',$pid);
		$us->addChild('entityInfo','Hackespace event id '.$pid);
	}

	/**
	 * Removes the substring "Category:" from the category property
	 */
	private function _removeCategoryPrefix( &$value ) {
		$value = substr( $value, strpos( $value, ':') + 1);
	}
	
		
	//FIXME: Do we even neeed to pass event here?
	private function _setDateTime( &$event, $startdate, $enddate ) {
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

	/**
	 * Ok, this is difficult for early-bird unless 
	 * we add distinct prices AND dates to the wiki pages.. 
	 * And we don't really want to do that now.
	 */
	private function _addTicketing( $event, $ticket_url, $startdate ) {
		$ticket = $event->addChild('tickets')->addChild('ticket');
		$ticket->addChild( 'datetime', date( 'c', strtotime($startdate) ) );
		$ticket->addChild( 'ticketUrl', $ticket_url );
		$contact = new Contact;
		$contact->addPhoneNumber( $ticket );
		$ticket->addChild( 'ticketInfo', 'Buy a ticket at the best rate for ' . $event->name );
	}
	
	private function _setPrices( $event, $cost ){
		// prices (if the price is 0 or something other than a numeric value, set freeOfCharge to true)
		$prices = $event->addChild('prices');
		$first = substr( $cost, 0, 1 );
		// everything that does not evaluate to something sensible is 0
		if ( (int) $cost == 0 ) {
			$prices->addAttribute( 'freeOfCharge','true' );
		} else {
			$prices->addAttribute( 'freeOfCharge', 'false' );
			$price = $prices->addChild('price');
			$price->addChild('priceDescription','Fee');
			$price->addChild('priceValue',(int) $cost);
		}
	}
}
