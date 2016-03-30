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
        foreach ($this->getLines() as $line) {

            $data = json_decode($line, true);

            if ($data['type'] == 'banners' && ($data['event'] == 'update' || $data['event'] == 'create')) {
                $this->createBanner($data);
            } else {
                $this->log->error("операция не поддерживается type={$data['type']} event={$data['event']} url={$data['url']}");
                continue;
            }
        }
    }

    protected function getLines()
    {
        $handle = bzopen($this->file, "r");
        if ($handle) {
            $decompressedData = '';
            while (true) {

                do {
                    if (feof($handle)) {
                        bzclose($handle);
                        return;
                    }

                    $decompressedData .= bzread($handle, 8192);
                    $key = strpos($decompressedData, "\n");
                } while ($key == false);

                do {
                    $line = substr($decompressedData, 0, $key + 1);
                    $decompressedData = substr_replace($decompressedData, '', 0, $key + 1);
                    yield $line;
                    $key = strpos($decompressedData, "\n");
                } while ($key != false);
            }
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