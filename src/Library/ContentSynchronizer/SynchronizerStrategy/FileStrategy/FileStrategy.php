<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\Bulk;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Library\ElasticsearchBulk;
use Client\Models\Urls;
use Client\Models\Contents;

class FileStrategy extends SynchronizerStrategy
{
    protected $typeMap = [
        'content' => Urls::CONTENT,
        'image' => Urls::IMAGE,
        'common' => Urls::COMMON
    ];

    protected $file;

    protected $fullUpdate = false;

    protected $bulkUrl;

    protected $bulkContent;

    public function __construct($file = null, $fullUpdate = false)
    {
        $this->file = $file;
        $this->fullUpdate = $fullUpdate;

        $this->bulkUrl = new Bulk('urls', ['url', 'state', 'type', 'action', 'created_at']);
        $this->bulkContent = new ElasticsearchBulk();
    }

    public function updateContent()
    {
        $startUpdatingTime = date(DATE_ISO8601);

        $this->handleFile();
        $this->deleteOldVersion($this->fullUpdate, $startUpdatingTime);
        $this->deleteContents();
        $this->nginxCacheClear();
        $this->deleteImages();
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

    protected function deleteOldVersion($all = true, $startUpdatingTime)
    {
        if ($all) {
            $this->log->info('Начали удаление старой версии');

            do {
                $result = $this->db->query("DELETE FROM contents WHERE created_at < ? LIMIT 1000", [$startUpdatingTime]);
                usleep(1000); //неблокируем select
            } while($result->numRows() > 0);

            $this->log->info('Удалили старую версию');
            return;
        }

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

    protected function deleteImages()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find(
            [
                'type = :type: AND action = :action:',
                'bind' => [
                    'type'   => Urls::IMAGE,
                    'action' => Urls::FOR_DELETING
                ]
            ]
        );

        foreach ($urls as $url) {
            unlink($this->config->imagesCacheDir . $url->url);
            $url->delete();
            $this->log->info("Удалена картинка url=" . $url->url);
        }
    }
}