<?php

namespace Client\Models;

class Urls extends Bulk
{
    const OPEN  = 1;
    const LOCK  = 2;
    const CLOSE = 3;
    const ERROR = 4;

    const CONTENT = 5;
    const IMAGE   = 6;
    const COMMON  = 7;

    const FOR_DELETING = 8;
    const FOR_PUT_WATERMARK = 9;
    const FOR_REPLACE_WATERMARK = 10;

    public $id;

    public $url;

    public $state;

    public $type;

    public $action;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }

    public function init($table = 'urls', array $columns = ['url', 'state', 'type', 'action', 'created_at'], $bufferSize = 1000)
    {
        parent::init($table, $columns, $bufferSize);
    }
    
    public function insert($url, $type = self::CONTENT, $action = self::FOR_PUT_WATERMARK)
    {
        $urls = [];
        $urls[] = $url;
        $urls[] = Urls::OPEN;
        $urls[] = $type;
        $urls[] = $action;
        $urls[] = date(DATE_ISO8601);

        parent::insert($urls);
    }
}
