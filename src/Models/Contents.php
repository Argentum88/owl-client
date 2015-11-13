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

    public static function get($url)
    {
        /** @var Contents $content */
        $content = Contents::findFirst([
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => $url,
                    'state' => Contents::READY,
                    'type' => Urls::CONTENT
                ],
                'order' => 'created_at DESC'
            ]);

        if (!$content) {
            return ['success' => false];
        }

        /** @var Contents $common */
        $common = Contents::findFirst([
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => ' ',
                    'state' => Contents::READY,
                    'type' => Urls::COMMON
                ],
                'order' => 'created_at DESC'
            ]);

        return json_decode($content->content, true)  + json_decode($common->content, true);
    }
}
