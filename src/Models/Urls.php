<?php

namespace Client\Models;

class Urls extends \Phalcon\Mvc\Model
{
    const OPEN  = 1;
    const LOCK  = 2;
    const CLOSE = 3;
    const ERROR = 4;

    const CONTENT = 5;
    const IMAGE   = 6;

    public $id;

    public $url;

    public $state;

    public $type;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }
}
