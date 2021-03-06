<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\ContentSynchronizer\SynchronizableInterface;
use Client\Models\Urls;
use Client\Models\Contents;

class PartialFileStrategy extends BaseFileStrategy implements SynchronizableInterface
{
    public function __construct($file = null)
    {
        $this->bulkContent = new Contents();
        $this->bulkContent->init();
        parent::__construct($file);
    }
    
    public function updateContent()
    {
        $startUpdatingTime = date(DATE_ISO8601);

        $this->handleFile();
        $this->deleteOldVersion($startUpdatingTime);
        $this->deleteContents();
        $this->nginxCacheClear();
    }

    protected function handleFile()
    {
        foreach ($this->getLines() as $line) {

            $data = json_decode($line, true);

            if (($data['type'] == 'content' || $data['type'] == 'common') && ($data['event'] == 'update' || $data['event'] == 'create')) {
                $this->bulkContent->insert($data);
            } elseif (($data['type'] == 'content' || $data['type'] == 'common') && $data['event'] == 'delete') {
                $url = !empty($data['url']) ? $data['url'] : ' ';
                $type = Contents::$typeMap[$data['type']];
                $this->bulkUrl->insert($url, $type, Urls::FOR_DELETING);
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
    
    protected function deleteOldVersion($startUpdatingTime)
    {
        $this->log->info('Начали удаление старой версии');

        /** @var Contents[] $contents */
        $contents = Contents::find([
            'created_at >= :created_at:',
            'bind' => [
                'created_at' => $startUpdatingTime
            ]
        ]);

        foreach ($contents as $content) {
            $oldContents = Contents::find([
                'url = :url: AND type = :type: AND created_at < :created_at:',
                'bind' => [
                    'url' => $content->url,
                    'type' => $content->type,
                    'created_at' => $startUpdatingTime
                ]
            ]);

            $oldContents->delete();
            $url = $content->url;
            $this->log->info("удалена старая версия $url");
        }

        $this->log->info('Удалили старую версию');
    }

    protected function deleteContents()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find(
            [
                '(type = :type1: OR type = :type2:) AND action = :action:',
                'bind' => [
                    'type1'   => Urls::CONTENT,
                    'type2'  => Urls::COMMON,
                    'action' => Urls::FOR_DELETING
                ]
            ]
        );

        foreach ($urls as $url) {
            $contentsForDeleting = Contents::find(
                [
                    'url = :url: AND type = :type:',
                    'bind' => [
                        'url'  => $url->url,
                        'type' => $url->type
                    ]
                ]
            );

            $contentsForDeleting->delete();
            $this->log->info("Удален контент url= . $url->url");
            $url->delete();
        }
    }
}