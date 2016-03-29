<?php

namespace Client\Models;

class Contents extends Bulk
{
    public $id;

    public $url;

    public $controller;

    public $action;

    public $content;

    public $type;

    public $created_at;

    public static $typeMap = [
        'content' => Urls::CONTENT,
        'image' => Urls::IMAGE,
        'common' => Urls::COMMON
    ];

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }

    public function init($table = 'contents', array $columns = ['url', 'controller', 'action', 'content', 'type', 'created_at'], $bufferSize = 1000)
    {
        parent::init($table, $columns, $bufferSize);
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

    public function insert($data)
    {
        $decodedContent = !empty($data['content'][1]) ? json_decode($data['content'][1], true) : null;

        $content = [];
        $content[] = !empty($data['url']) ? $data['url'] : ' ';
        $content[] = !empty($decodedContent['controller']) ? $decodedContent['controller'] : ' ';
        $content[] = !empty($decodedContent['action']) ? $decodedContent['action'] : ' ';
        $content[] = !empty($data['content'][1]) ? $data['content'][1] : ' ';
        $content[] = static::$typeMap[$data['type']];
        $content[] = date(DATE_ISO8601);

        parent::insert($content);
    }
}
