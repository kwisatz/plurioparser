<?php

/**
 * Description of appindexer
 *
 * @author kwisatz
 */

namespace Core\Lib;

class AppIndexer {
    
    public static function index() {
        $apps = array();
        $appdir = realpath('apps');
        $dh = opendir( $appdir );
        while($file = readdir($dh)){
            if( preg_match('/^\.+.*/',$file ) ) continue; // filter everything starting with a dot
            if( is_dir( $appdir. DS .$file ) ) {
                $apps[] = $file;
            }
        }
        return $apps;
    }
}

?>
