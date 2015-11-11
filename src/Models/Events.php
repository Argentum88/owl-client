<?php

namespace Client\Models;

class Events extends \Phalcon\Mvc\Model
{
    const FULL_UPDATE_CONTENT  = 1;
    const UPDATE_CONTENT  = 2;
    const CLEAR_CACHE = 3;

    const OPEN = 4;
    const PROCESSING = 5;
    const DONE = 6;
    const ERROR = 7;

    public $id;

    public $type;

    public $state;

    public $data;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }
}
