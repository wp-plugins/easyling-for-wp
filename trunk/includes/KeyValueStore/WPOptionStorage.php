<?php

class WPOptionStorage extends KeyValueStorage {

    public function __construct($itemType) {
        parent::__construct($itemType);
    }

    protected function _get($key) {
        return get_option($this->_prefixOptionKey($key), false);
    }

    protected function _has($key) {
        return !get_option($this->_prefixOptionKey($key), false) ? false : true;
    }

    protected function _put($key, $value) {
        update_option($this->_prefixOptionKey($key), $value);
    }

    protected function _remove($key) {
        delete_option($this->_prefixOptionKey($key));
    }

    private function _prefixOptionKey($key) {
        return 'easyling_' . $key;
    }

    protected function _removeAll() {
        global $wpdb;
        $query = "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'easyling_%'";
        $wpdb->query($query);
    }

    protected function _lock() {
        
    }

    protected function _unlock() {
        
    }

}