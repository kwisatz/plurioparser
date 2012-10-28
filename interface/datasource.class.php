<?php

interface Interface_DataSource {
	public function getIdFor( $entity, $caller );
	public function fetchLocationInfo( $locationID );
}
