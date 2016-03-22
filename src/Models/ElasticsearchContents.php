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
        $bool = new \Elastica\Query\BoolQuery();

        $urlTerm = new \Elastica\Query\Term(['url' => $url]);
        $typeTerm = new \Elastica\Query\Term(['type' => Urls::CONTENT]);
        $bool->addMust($urlTerm)->addMust($typeTerm);
        $query = new \Elastica\Query();
        $query->setQuery($bool);

        /** @var \Elastica\Type $type */
        $type = \Phalcon\Di::getDefault()->get('elastica')->getIndex('owl')->getType('owl');
        $content = $type->search($query)->current()->getSource()['content'];







        $bool = new \Elastica\Query\BoolQuery();

        $urlTerm = new \Elastica\Query\Term(['url' => ' ']);
        $typeTerm = new \Elastica\Query\Term(['type' => Urls::COMMON]);
        $bool->addMust($urlTerm)->addMust($typeTerm);
        $query = new \Elastica\Query();
        $query->setQuery($bool);

        /** @var \Elastica\Type $type */
        $type = \Phalcon\Di::getDefault()->get('elastica')->getIndex('owl')->getType('owl');
        $common = $type->search($query)->current()->getSource()['content'];







        return json_decode($content, true)  + json_decode($common, true);
    }
}
