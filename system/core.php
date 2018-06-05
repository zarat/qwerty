<?php

/**
 * @author Manuel Zarat
 */

abstract class core {               
 
    private $db = false;     
    private $last_insert_id = false; 

    /**
     * @todo Unterstuetzung verschiedener Datenbanktypen wie MySQL, SQLite,.. u.a.
     */
    final function __construct() {              
        if(is_file(ABSPATH . "config.php")) {         
            include ABSPATH . "config.php";             
        } else {         
            include ABSPATH . "install.php"; 
            exit();             
        }                        
        $this->db = new mysqli($dbhost,$dbuser,$dbpass,$dbname);                                                         
    }

    final function __destruct() {              
        if($this->db) {        
            $this->db->close();            
            $this->db = false;            
        }                                                         
    }
    
    final function login($uid, $pass) {    
        $user = $this->select( array( "select" => "*", "from" => "user", "where" => "email='$uid' AND password='$pass'") );        
        return !empty( $user[0]['id'] ) ? $user[0]['id'] : false;        
    }
    
    final function logout() {    
        $token = $_COOKIE['sp-uid'];        
        $this->update( array( "table" => "user", "set" => "token='' where token='$token'" ) );        
    }
    
    final function auth() {    
        $token = @$_COOKIE['sp-uid'];        
        $user = $token ? $this->select( array( "select" => "*", "from" => "user", "where" => "token='$token'") ) : false;        
        return !empty( $user[0]['id'] ) ? $user[0] : false;       
    }  
     
    private function sql_escape_string($query) {    
        return mysqli_real_escape_string($this->db, $query);            
    }   
         
    private function query($query) {     
        $this->sql_escape_string($query);        
        return $this->db->query($query);        
    }
    
    private function fetch($sql) {    
        return $sql->fetch_array();        
    }
    
    private function fetch_assoc($sql) {    
        return $sql->fetch_assoc();       
    }
    
    final function last_insert_id() {    
        return $this->last_insert_id;        
    }   
    
    final function insert($config) {    
        extract($config);       
        $this->query( "INSERT INTO $insert VALUES $values" );
        $this->last_insert_id = $this->db->insert_id;
        return $this->last_insert_id;
    }
    
    final function update($config) {
        extract($config);
        return $this->query( "UPDATE $table SET $set" );
    }    
    
    final function delete( $config ) {
        extract( $config );
        return $this->query( "DELETE FROM $from WHERE $where" );
    }
    
    final function select($config) {
        extract($config);
        $arr = array();
        $query = $this->query( "SELECT $select FROM $from WHERE $where" );
        while( $row = $this->fetch_assoc( $query ) ) {
            $arr[] = $row;
        }
        return ( $arr ) ? $arr : false;
    }
 
    /**
     * Gibt ein assoziatives Array mit Einstellungen aus der Tabelle 'settings' zurück. 
     * Wenn der Parameter key angegeben wurde, wird nur der jeweilige Datensatz ausgegeben.
     */
    final function settings($key = false) { 
        $query = $this->query( ( false !== $key ) ? "SELECT * FROM settings WHERE settings.key='$key'" : "SELECT * FROM settings" );
        $arr = false;
        while( $row = $this->fetch( $query ) ) {
            $arr[$row['key']] = $row['value'];
        }   
        return $key ? $arr[$key] : $arr;
    }
    
    /**
     * Ein assoziatives Array eines Items.
     * 
     * Kann mit oder ohne Metadaten aufgerufen werden.
     * Dazu einen Index 'metadata' im Array(config) anlegen.
     */
    final function single( $config ) { 
        extract($config);
        $item = $this->fetch_assoc( $this->query( "SELECT * FROM item WHERE id=$config[id]" ) ); 
        if( !$item["id"] ) { return false; };
        if( @$metadata && $metas = $this->single_meta($item['id'])) {
            foreach( $metas as $k => $v ) {
                $item[$k] = $v;
            }
        }
        return isset($item['id']) ? $item : false;
    }
    
    /**
     * Ein assoziatives Array aller Metadaten zu einem Item.
     * 
     * Wird der Parameter index auf true gesetzt, wird die jeweilige ID in Array mit ausgegeben. 
     * In dem Fall kann es nicht mehr an ein Item eangehängt werden! 
     */
    final function single_meta($item_id,$index=false) {    
        if($index) {        
            $item_meta = $this->query("SELECT meta_id, meta_key as k, meta_value as v FROM item_meta WHERE meta_item_id=$item_id");             
        } else {        
            $item_meta = $this->query("SELECT meta_key as k, meta_value as v FROM item_meta WHERE meta_item_id=$item_id");            
        }        
        $metadata = false;       
        while($metas = $this->fetch_assoc($item_meta)) {        
            $metadata[$metas['k']] = $metas['v'];           
        }              
        return ($metadata) ? $metadata : false;        
    }

    final function archive($config) {    
        extract($config);        
        $archive = false;        
        if($items = $this->query("SELECT $select FROM $from WHERE $where")) {        
            while($item = $this->fetch($items)) {                                   
                $archive[] = $item;                 
            }            
        }
        return (false !== $archive) ? $archive : false;        
    }
    
    /**
     * @todo Prueft nur, welche Ordner in ../content/themes/* enthalten sind.
     */
    final function installed_themes() {         
        if( $files = opendir( ABSPATH . 'content' . DS . 'themes') ) {             
            while ( false !== ( $file = readdir( $files ) ) ) {             
                if ( $file != '.' && $file != '..' ) {                
                    $themes[] = $file;                        
                }                             
            }                              
            closedir($files);                           
        }                   
        return $themes ? $themes : false;                  
    }
    
    /**
     * Parst den Querystring und gibt ihn als Array zurueck - sonst false
     * Wenn ein Parameter uebergeben wird, wird dieser (wenn vorhanden) zurueckgegeben - sonst false
     * 
     * @todo SQL injection fix
     */
    final function request($key=false) {   
        if($_SERVER['QUERY_STRING']) {
            parse_str($_SERVER['QUERY_STRING'], $parameters);            
            if(false !== $key) {            
                if(!empty($parameters[$key])) {                       
                    if($key == 'id') {                    
                        return (int)$parameters[$key];                        
                    } else {                    
                        return $parameters[$key];                       
                    }                   
                } else {               
                    return false;                                
                }                        
            } else {              
                return ($parameters) ? $parameters : false;                    
            }                 
        }           
    }
        
}

?>
