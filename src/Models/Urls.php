<?php

namespace Client\Models;

class Urls extends \Phalcon\Mvc\Model
{
    const CONTENT = 5;
    const IMAGE   = 6;
    const COMMON  = 7;

    const FOR_DELETING = 8;
    const FOR_UPDATING = 9;

    public $id;

    public $url;

    public $type;

    public $action;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }
}
