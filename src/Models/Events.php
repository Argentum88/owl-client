<?php

namespace Client\Models;

class Events extends \Phalcon\Mvc\Model
{
    const UPDATE_CONTENT  = 1;
    const CLEAR_CACHE = 2;

    const OPEN = 3;
    const PROCESSING = 4;
    const DONE = 5;
    const ERROR = 6;

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
