<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\Bulk;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Urls;
use Client\Models\Contents;

class BaseFileStrategy extends SynchronizerStrategy
{
    protected $typeMap = [
        'content' => Urls::CONTENT,
        'image' => Urls::IMAGE,
        'common' => Urls::COMMON
    ];

    protected $file;

    /**
     * @var Bulk
     */
    protected $bulkUrl;

    /**
     * @var Bulk
     */
    protected $bulkContent;

    public function __construct($file = null)
    {
        $this->file = $file;
    }

    public function updateBanner()
    {
        $this->handleFile();
    }

    protected function handleFile()
    {
        $handle = fopen($this->file, "r");
        if ($handle) {

            while (($line = fgets($handle)) !== false) {

                $data = json_decode($line, true);

                if ($data['type'] == 'banners' && ($data['event'] == 'update' || $data['event'] == 'create')) {
                    $this->createBanner($data);
                } else {
                    $this->log->error("операция не поддерживается type={$data['type']} event={$data['event']} url={$data['url']}");
                    continue;
                }
            }

            fclose($handle);
        } else {
            throw new \Exception("не удалось открыть файл");
        }
    }

    protected function createBanner($data)
    {
        $banners = json_decode($data['content'][1], true);
        parent::createBanner($banners);
    }

    protected function createContent($data)
    {
        $decodedContent = !empty($data['content'][1]) ? json_decode($data['content'][1], true) : null;

        $content = [];
        $content[] = !empty($data['url']) ? $data['url'] : ' ';
        $content[] = !empty($decodedContent['controller']) ? $decodedContent['controller'] : ' ';
        $content[] = !empty($decodedContent['action']) ? $decodedContent['action'] : ' ';
        $content[] = !empty($data['content'][1]) ? $data['content'][1] : ' ';
        $content[] = $this->typeMap[$data['type']];
        $content[] = date(DATE_ISO8601);

        $this->bulkContent->insert($content);
    }

    public function createUrl($url, $type = Urls::CONTENT, $action = Urls::FOR_PUT_WATERMARK)
    {
        $urls = [];
        $urls[] = $url;
        $urls[] = Urls::OPEN;
        $urls[] = $type;
        $urls[] = $action;
        $urls[] = date(DATE_ISO8601);

        $this->bulkUrl->insert($urls);
    }
}