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
	public function __construct( $agenda, $buildings, $orgs ) {
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
	public function createNewFromItem( $item ) {
		global $config;

		$this->_event->addChild( 'name', $item->label );
		if( !empty( $item->has_subtitle ) )
			$this->_event->addChild( 'subtitleOne', $item->has_subtitle[0] );
			
		// not nice, but functional FIXME
		$locid = !empty( $item->has_location_id[0] ) ? $item->has_location_id[0] : $item->has_location[0];
		$linfo = $this->fetchLocationInfo( $locid );
		$this->_event->addChild( 'localDescription', $linfo->has_localDescription[0] );

		// XML Schema says short description must come before long description
		$desc = new Descriptions( $this->_event );
		if( !empty( $item->has_subtitle[0] ) )
			$desc->setShortDescription( 'en', $item->has_subtitle[0] );
		$desc->setLongDescription( 'en', $item->has_description[0] );

		// Add date and time
		$this->_setDateTime( $item->startdate[0], $item->enddate[0] );

		// Add prices
		$this->_setPrices( $this->_event, $item->has_cost[0] );

		// Add ticketing (if available)
		if( !empty( $item->has_ticket_url[0] ) ) {
			$this->_addTicketing( 
				$this->_event, 
				$item->has_ticket_url[0], 
				$item->startdate[0]
			);
		}
		
		/****** <contactEvent/> ******/
		$contact = new Contact;

		// use external website if supplied, else the webbase from the config + entity label
		// FIXME: if ( RegXor::isWebsite( $website ) );
		if ( !empty( $item->url[0] ) ) {
			$contact->setWebsiteUrl( $item->url[0] );
		} elseif ( $config['events.havewebsite'] === 'true' && !empty( $config['events.webbase'] ) ) {
			$contact->setWebsiteUrl( $config['org.webbase'] . str_replace( ' ', '_', $item->label ) );
		} else {
			$contact->setWebsiteUrl( null );	// effectively removes url
		}

		// add Email Address if one was supplied, else use the default from the config
		// FIXME: add email by category?!
		if ( !empty( $item->has_contact[0] ) && RegXor::isEmail( $item->has_contact[0] ) ) {
			$contact->setEmailAddress( $item->has_contact[0] );
		} elseif ( !empty( $config['org.email'] ) ) {
			$contact->setEmailAddress( $config['org.email'] );
		}

		// Finally add contact xml subtree to the event tree
		$contact->addTo( $this->_event, $this );

		/****** <relationsAgenda/> ******/
		$relations = $this->_event->addChild('relationsAgenda');

		/***** RelationsAgenda :: BUILDING ******/
		/**
		 * Our wiki semantics don't support internal events right now, 
		 * so no <internalEvents/> here either
		 * But we do have multiple locations, so for each location, 
		 * we need to check whether it already exists in the guide,
		 * and if not, add it
		 *
		 * i.e. add a new building to the guide if it's not already
		 * in there - or at least try to
		 */
		$building = new Building;
		$locid = !empty( $item->has_location_id[0] ) ? $item->has_location_id[0] : $item->has_location[0];
		if( !$building->_inGuide( $locid ) ){
			$buildingExtId = $building->addToGuide( 
				$this->_buildings, 
				$locid, 
				$item->has_organizer[0] );
		} else $buildingExtId = $building->getIdFor( $locid );

		// If adding to the guide or retrieving the Id was successful, add a reference
		if( $buildingExtId != NULL ) {
			$place = $relations->addChild('placeOfEvent');	// mandatory
			$place->addAttribute('isOrganizer','false');	// as directed by guideline
			$place->addChild('extId', $buildingExtId );
		} else { 
			// we're DOOMED!! remove the entire event since we're unable to 
			// reference it to a location
			throw new Exception( sprintf( "Could not add location for event %s to guide section! ABORTING\n", $item->label ) );
		}

		/****** RelationsAgenda :: <personsToEvent/> ******/
		// none right now

		/****** RelationsAgenda :: ORGANISATION	<organisationsToEvent/> ******/
		$orga = $relations->addChild('organisationsToEvent')->addChild('organisationToEvent');

		// FIXME FIXME FIXME: put this check into Organisation(Builder) FIXME
		$organisation = new Organisation;
		if ( $organisation->_inGuide( $item->has_organizer[0] ) ) {
			$organisationExtId = $organisation->getIdFor( $item->has_organizer[0] );
		} else {
			$organisationExtId = $organisation->addToGuide( $this->_orgs, $item->has_organizer[0] );
		} 
			
		if ( $organisationExtId ) {
			$orga->addChild('extId',$organisationExtId );
			$orga->addChild('organisationRelEventTypeId','oe07');	// = organiser
		}

		// FIXME: put into private method
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

		// FIXME: movies?! (http://xml.syyncplus.net/14/intern/news/version-1-6.html)

		// <agendaCategories/> - can have as many as we want
		$this->_addCategories( $relations, $item->is_event_of_type[0], $item->category );

		// set userspecific (unique ids)
		$us = $this->_event->addChild('userspecific');
		$pid = 'ev' . $this->getIdFor( $item->label );
		$us->addChild( 'entityId', $pid);
		$us->addChild( 'entityInfo', $config['org.name'] . ' event id ' . $pid );

		return true;
	}


	/**
	 * Add agendaCategories to the Event
	 * FIXME: this is still pretty mediawiki specific
	 */
	protected function _addCategories( &$relations, $eventType, $eventCategory ) {
		$categories = $relations->addChild('agendaCategories');
			
		// map our categories and event types to the corresponding plurio ones
		$mwtypes = is_array( $eventType ) ? $eventType : array( $eventType );
		$mwcats = is_array( $eventCategory) ? $eventCategory : array( $eventCategory );

		array_walk( $mwcats, 'self::_removeCategoryPrefix' );
		$mwcats = array_unique( array_merge( $mwtypes, $mwcats ) );

		foreach( $mwcats as $mwc ) {
			if($mwc == 'RecurringEvent') continue;	// filter recurring event category
			foreach($this->_mapCategory($mwc) as $pcats)
				$categories->addChild('agendaCategoryId',$pcats);
		}
	}

	/**
	 * Removes the substring "Category:" from the category property
	 */
	private function _removeCategoryPrefix( &$value ) {
		if ( strpos( $value, ':') ) {
			$value = substr( $value, strpos( $value, ':') + 1);
		}
	}
	
		
	private function _setDateTime( $startdate, $enddate ) {
		list( $dateFrom, $timingFrom ) = $startdate;
		list( $dateTo, $timingTo ) = $enddate;

		$date = $this->_event->addChild('date');
		$date->addChild('dateFrom',$dateFrom);
		$date->addChild('dateTo',$dateTo);
		$date->addChild('dateExclusions');

		$timing = $this->_event->addChild('timings')->addChild('timing');
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
