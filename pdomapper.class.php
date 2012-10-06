<?php

class PDOMapper implements DataSource {

	private $_dbh;	// db handle


	public function __construct(){
		$this->_dbh = new PDO( $config['pdo.dsn'] );
	}

	public function getInitialData() {
	}

	private function _doQuery() {
	
		//$stmt = $dbh->prepare('SELECT x,y,z FROM table WHERE a=:value');
		//$stmt->bindParam(':value', 'bla');
		//$res = $stmt->execute();
		//$stmt = $dbh->prepare('SELECT x,y,z FROM table WHERE a=?');
		//$stmt->execute( $value )
		//while ( $row = $stmt->fetch() ) {
		$res = $dbh->query('SELECT x,y,z FROM table WHERE a="bla"');
	}

}
