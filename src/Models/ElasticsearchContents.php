<?php

namespace Client\Models;

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

        $curl = curl_init();

        $opt = [
            CURLOPT_URL => 'http://' . $config->elasticsearch->connection->host . ':' . $config->elasticsearch->connection->port . '/owl/owl/_search',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($query),
            CURLOPT_TIMEOUT => 8,
            CURLOPT_RETURNTRANSFER => true,
        ];

        curl_setopt_array($curl, $opt);

        $response  = curl_exec($curl);
        $content = json_decode($response, true)['hits']['hits'][0]['_source']['content'];







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

        $opt = [
            CURLOPT_POSTFIELDS => json_encode($query),
        ];

        curl_setopt_array($curl, $opt);

        $response  = curl_exec($curl);
        $common = json_decode($response, true)['hits']['hits'][0]['_source']['content'];

        curl_close($curl);






        return json_decode($content, true)  + json_decode($common, true);
    }
}
