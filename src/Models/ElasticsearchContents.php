<?php

namespace Client\Models;

use cURL\Request;

/**
 * Class ElasticsearchContents
 * @package Client\Models
 */
class ElasticsearchContents
{
    public static function get($url)
    {
        $config = \Phalcon\Di::getDefault()->get('config');

        $query = [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['url' => $url]],
                        ['term' => ['type' => Urls::CONTENT]]
                    ]
                ]
            ]
        ];

        $request = new Request('http://' . $config->elasticsearch->connection->host . ':' . $config->elasticsearch->connection->port . '/owl/owl/_search');
        $request->getOptions()
            ->set(CURLOPT_POST, true)
            ->set(CURLOPT_POSTFIELDS, json_encode($query))
            ->set(CURLOPT_TIMEOUT, 8)
            ->set(CURLOPT_RETURNTRANSFER, true);

        $response = $request->send();
        $content = json_decode($response->getContent(), true)['hits']['hits'][0]['_source']['content'];





        $query = [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['url' => ' ']],
                        ['term' => ['type' => Urls::COMMON]]
                    ]
                ]
            ]
        ];

        $request->getOptions()->set(CURLOPT_POSTFIELDS, json_encode($query));

        $response = $request->send();
        $common = json_decode($response->getContent(), true)['hits']['hits'][0]['_source']['content'];






        return json_decode($content, true)  + json_decode($common, true);
    }
}
