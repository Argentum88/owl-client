<?php

namespace Client\Library;

use Phalcon\Di\Injectable;

/**
 * Class ElasticsearchBulk
 * @package Client\Library
 * @property \Elastica\Client $elastica
 */
class ElasticsearchBulk extends Injectable
{
    protected $rowBuffer = [];

    protected $bufferSize;

    public function __construct($bufferSize = 1000)
    {
        $this->bufferSize = $bufferSize;
    }

    public function insert(array $row)
    {
        $data['url'] = $row[0];
        $data['content'] = $row[3];
        $data['type'] = $row[4];
        $data['created_at'] = $row[5];

        $doc = new \Elastica\Document('', $data);

        $this->putInBuffers($doc);
    }

    protected function putInBuffers($doc)
    {
        $this->rowBuffer[] = $doc;
        
        if (count($this->rowBuffer) >= $this->bufferSize) {
            $this->flash();
        }
    }

    public function flash()
    {
        if (count($this->rowBuffer) == 0) {
            return;
        }
        
        try {
            $this->elastica->getIndex('owl')->getType('owl')->addDocuments($this->rowBuffer);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
        }

        $this->rowBuffer = [];

        $this->log->info('применили пачку');
    }
}