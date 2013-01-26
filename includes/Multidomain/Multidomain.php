<?php

class MultiDomain {

    public $domains;
    public $domain;
    public $conditional_fired;

    public function __construct($config) {
        $this->domains = $config;

        $this->get_domain();

        // Register Filters
        if ($this->domain) {
            $properties = array('blogname', 'siteurl', 'home', 'blogdescription', 'template');

            foreach ($properties as $property) {
                if (isset($this->domain[$property]) && $this->domain[$property]) {
                    add_filter("option_$property", array($this, $property), 1);

                    if ($property == 'template') {
                        add_filter("template", array($this, "template"), 1);
                        add_filter("option_stylesheet", array($this, "template"), 1);
                    }
                }
            }
        }
    }

    public function current_domain() {
        if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST']))
            return $_SERVER['HTTP_HOST'];
        return $_SERVER['SERVER_NAME'];
    }

    public function get_domain() {
        $domains = $this->domains;

        if ($domains) {
            foreach ($domains as $domain) {
                if ($this->current_domain() == $domain['domain']) {
                    $this->domain = $domain;
                }
            }
        }
    }

    /* Set Site Properties */

    public function blogname() {
        return $this->domain['blogname'];
    }

    public function siteurl() {
        return $this->domain['siteurl'];
    }

    public function home() {
        return $this->domain['home'];
    }

    public function blogdescription() {
        return $this->domain['blogdescription'];
    }

    public function stylesheet() {
        return $this->domain['stylesheet'];
    }

    public function template() {
        return $this->domain['template'];
    }

}

?>