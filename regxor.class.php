<?php

class RegXor {

	public static function isValidEmail( $email ) {
		if ( preg_match( '/^[^\W][a-zA-Z0-9-_]+(\.[a-zA-Z0-9-_]+)*\@[a-zA-Z0-9-_]+(\.[a-zA-Z0-9-_]+)*\.[a-zA-Z]{2,4}$/', $email ) ) {
			return true;
		} else return false;
	}

}
