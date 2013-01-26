<?php

require_once dirname(__FILE__) . '/../ptm/KeyValueStorage/KeyValueStorage.php';

class WPDbStorage extends KeyValueStorage {

    /**
     * WP Db
     * @var wpdb 
     */
    private $db;

    public function __construct($itemType) {
        parent::__construct($itemType);
        global $wpdb;
        $this->db = $wpdb;
    }

    protected function _get($key) {
        $query = "SELECT easyling_value FROM {$this->db->prefix}easyling WHERE easyling_key = '{$key}'";
        $res = $this->db->get_col($query, 0);
        if ($res[0])
            return unserialize($res[0]);
        return null;
    }

    protected function _has($key) {
        $k = $this->_get($key);
        $has = empty($k) ? false : true;
        return $has;
    }

    protected function _put($key, $value) {
        $query = "INSERT INTO {$this->db->prefix}easyling VALUES(%s,%s) ON DUPLICATE KEY UPDATE easyling_value = %s";
        $serVal = serialize($value);
        $query = $this->db->prepare($query, $key, $serVal, $serVal);
        $this->db->query($query);
    }

    protected function _remove($key) {
        $query = "DELETE FROM {$this->db->prefix}easyling WHERE easyling_key = '$key' LIMIT 1";
        $this->db->query($query);
    }

    protected function _removeAll() {
        $query = "TRUNCATE TABLE {$this->db->prefix}easyling";
        $this->db->query($query);
    }
    
    protected function _lock(){
        $query = "LOCK TABLES {$this->db->prefix}easyling WRITE";
        $this->db->query($query);
    }
    
    protected function _unlock() {
        $query = "UNLOCK TABLES";
        $this->db->query($query);
    }

}