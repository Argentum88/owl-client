<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\Bulk;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Urls;
use Client\Models\Contents;

class FullFileStrategy extends BaseFileStrategy
{
    public function __construct($file = null)
    {
        $this->bulkUrl = new Bulk('urls', ['url', 'state', 'type', 'action', 'created_at']);
        $this->bulkContent = new Bulk('contents_new', ['url', 'controller', 'action', 'content', 'type', 'created_at']);

        parent::__construct($file);
    }

    public function updateContent()
    {
        $this->createNewTable();
        $this->handleFile();
        $this->apply();
        $this->nginxCacheClear();
    }

    protected function handleFile()
    {
        $handle = fopen($this->file, "r");
        if ($handle) {

            while (($line = fgets($handle)) !== false) {

                $data = json_decode($line, true);

                if (($data['type'] == 'content' || $data['type'] == 'common') && ($data['event'] == 'update' || $data['event'] == 'create')) {
                    $this->createContent($data);
                } elseif (($data['type'] == 'content' || $data['type'] == 'common') && $data['event'] == 'delete') {
                    $url = !empty($data['url']) ? $data['url'] : ' ';
                    $type = $this->typeMap[$data['type']];
                    $this->createUrl($url, $type, Urls::FOR_DELETING);
                } elseif ($data['type'] == 'image' && ($data['event'] == 'update' || $data['event'] == 'create')) {
                    if (!file_exists($this->config->imagesCacheDir . $data['url'])) {
                        $this->createUrl($data['url'], Urls::IMAGE);
                    }
                } elseif ($data['type'] == 'image' && $data['event'] == 'delete') {
                    if (file_exists($this->config->imagesCacheDir . $data['url'])) {
                        $this->createUrl($data['url'], Urls::IMAGE, Urls::FOR_DELETING);
                    }
                } elseif ($data['type'] == 'banners' && ($data['event'] == 'update' || $data['event'] == 'create')) {
                    $this->createBanner($data);
                } else {
                    $this->log->error("операция не поддерживается type={$data['type']} event={$data['event']} url={$data['url']}");
                    continue;
                }

                usleep(200); //снижение нагрузки на cpu
            }

            fclose($handle);
            $this->bulkContent->flash();
            $this->bulkUrl->flash();
        } else {
            throw new \Exception("не удалось открыть файл");
        }
    }

    protected function createNewTable()
    {
        $this->db->execute(
          "CREATE TABLE `contents_new` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `url` varchar(250) NOT NULL,
            `controller` varchar(150) NOT NULL,
            `action` varchar(150) NOT NULL,
            `content` longblob NOT NULL,
            `type` tinyint(1) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `url` (`url`(100)),
            KEY `created_at` (`created_at`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
        );
    }

    protected function apply()
    {
        $this->db->execute("RENAME TABLE contents TO contents_old, contents_new TO contents;");
        $this->db->execute("DROP TABLE contents_old;");
    }
}