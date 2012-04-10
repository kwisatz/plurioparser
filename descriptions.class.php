<?php
/**
 * @author David Raison
 * @ingroup plurio
 */

class Descriptions {

	private $_entity;	// reference to passed in entity
	private $_sdescs;
	private $_ldescs;

	public function __construct( &$entity ){
		$this->_entity = $entity;
	}


	/**
         * Short descriptions are fetched from the wiki Has_subtitle property
         */
        public function setShortDescription( $lang, $desc ){
                if(!isset( $this->_sdescs ))
                        $this->_sdescs = $this->_entity->addChild('shortDescriptions');

                $tdesc = $this->_sdescs->addChild('shortDescription', $desc);
                $tdesc->addAttribute('language', $lang );
        }

        /**
         * Long descriptions are fetched from the wiki Has_description property
         */
        public function setLongDescription( $lang, $desc ) {
                if(!isset( $this->_ldescs ))
                        $this->_ldescs = $this->_entity->addChild('longDescriptions');

                $lde = $this->_ldescs->addChild('longDescription', $desc );
                $lde->addAttribute('language', $lang );
        }
}
