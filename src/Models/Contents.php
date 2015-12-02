<?php

namespace Client\Models;

class Contents extends \Phalcon\Mvc\Model
{
    public $id;

    public $url;

    public $controller;

    public $action;

    public $content;

    public $type;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }

    public static function get($url)
    {
        /** @var Contents $content */
        $content = Contents::findFirst([
                'url = :url: AND type = :type:',
                'bind' => [
                    'url' => $url,
                    'type' => Urls::CONTENT
                ],
                'order' => 'created_at ASC'
            ]);

        if (!$content) {
            return ['success' => false];
        }

        /** @var Contents $common */
        $common = Contents::findFirst([
                'url = :url: AND type = :type:',
                'bind' => [
                    'url' => ' ',
                    'type' => Urls::COMMON
                ],
                'order' => 'created_at ASC'
            ]);

        return json_decode($content->content, true)  + json_decode($common->content, true);
    }
}
