<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\ContentSynchronizer\BannerUpdatableInterface;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Contents;
use Client\Models\Urls;

class BaseFileStrategy extends SynchronizerStrategy implements BannerUpdatableInterface
{
    protected $file;

    /**
     * @var Urls
     */
    protected $bulkUrl;

    /**
     * @var Contents
     */
    protected $bulkContent;

    public function __construct($file = null)
    {
        $this->file = $file;
        $this->bulkUrl = new Urls();
        $this->bulkUrl->init();
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
}