<?php

namespace Client\Models;

class Contents extends \Phalcon\Mvc\Model
{
    const READY = 1;
    const UPDATING = 2;

    public $id;

    public $url;

    public $controller;

    public $action;

    public $content;

    public $type;

    public $state;

    public $version;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }
}
