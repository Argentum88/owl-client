<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\ContentSynchronizer\SynchronizableInterface;
use Client\Models\Contents;
use Client\Models\Urls;

class FullFileStrategy extends BaseFileStrategy implements SynchronizableInterface
{
    public function __construct($file = null)
    {
        $this->bulkContent = new Contents();
        $this->bulkContent->init('contents_new');
        parent::__construct($file);
    }

    public function updateContent()
    {
        $this->createNewTable();
        $this->handleFile();
        $this->switchTables();
        $this->nginxCacheClear();
    }

    protected function handleFile()
    {
        foreach ($this->getLines() as $line) {

            $data = json_decode($line, true);

            if (($data['type'] == 'content' || $data['type'] == 'common') && ($data['event'] == 'update' || $data['event'] == 'create')) {
                $this->bulkContent->insert($data);
            } elseif ($data['type'] == 'image' && ($data['event'] == 'update' || $data['event'] == 'create')) {
                if (!file_exists($this->config->imagesCacheDir . $data['url'])) {
                    $this->bulkUrl->insert($data['url'], Urls::IMAGE);
                }
            } else {
                $this->log->error("операция не поддерживается type={$data['type']} event={$data['event']} url={$data['url']}");
                continue;
            }

            usleep(200); //снижение нагрузки на cpu
        }

        $this->bulkContent->flash();
        $this->bulkUrl->flash();
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
            KEY `url` (`url`(200)),
            KEY `created_at` (`created_at`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
        );
    }

    protected function switchTables()
    {
        $this->log->info('Начали переключение версий');
        $this->db->execute("RENAME TABLE contents TO contents_old, contents_new TO contents;");
        $this->db->execute("DROP TABLE contents_old;");
        $this->log->info('Переключили версии');
    }
}