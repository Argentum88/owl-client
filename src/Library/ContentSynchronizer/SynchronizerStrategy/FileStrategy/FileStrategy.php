<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\SynchronizerStrategy;
use Client\Models\Events;
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

    public function __construct($file = null, $fullUpdate = false)
    {
        $this->file = $file;
        $this->fullUpdate = $fullUpdate;
    }

    public function updateContent()
    {
        $this->handleFile();
        $this->setReadyState();
        $this->deleteFirstVersion($this->fullUpdate);
        $this->moveSecondVersionToFirst();
        $this->deleteContents();
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
            $count = 0;
            $this->db->begin();

            while (($line = fgets($handle)) !== false) {
                $count++;
                if ($count > 1000) {
                    $this->db->commit();
                    $this->db->begin();
                    $count = 1;
                    $this->log->info('применили транзакцию синхронизации контента');
                }

                $data = json_decode($line, true);

                if (($data['type'] == 'content' || $data['type'] == 'common') && ($data['event'] == 'update' || $data['event'] == 'create')) {
                    $this->createContent($data);
                } elseif (($data['type'] == 'content' || $data['type'] == 'common') && $data['event'] == 'delete') {
                    $url = !empty($data['url']) ? $data['url'] : ' ';
                    $this->createUrl($url, Urls::CONTENT, Urls::FOR_DELETING);
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
                    $this->log->error("операция не поддерживается");
                    continue;
                }
            }

            fclose($handle);
            $this->db->commit();
        } else {
            $this->log->error("не удалось открыть файл");
            exit();
        }
    }

    protected function createBanner($data)
    {
        $banners = json_decode($data['content'][1], true);

        foreach ($banners['banners'] as $placeName => $place) {
            $bodyContent =
                '
<?php
    $userAgent = isset($_SERVER[\'HTTP_USER_AGENT\']) ? $_SERVER[\'HTTP_USER_AGENT\'] : \'\';
?>

<?php if (preg_match(\'%s\', $userAgent)): ?>
%s
<?php else: ?>
%s
<?php endif; ?>
';

            $bodyContent = sprintf(
                $bodyContent,
                $place['regexp']['regexp'],
                $place['regexp']['body'],
                $place['default']['body']
            );

            file_put_contents($this->config->application->bannersDir . "$placeName.php", $bodyContent);
        }

        $headContent =
            '
<?php
    $userAgent = isset($_SERVER[\'HTTP_USER_AGENT\']) ? $_SERVER[\'HTTP_USER_AGENT\'] : \'\';
?>
';
        file_put_contents($this->config->application->bannersDir . "head.php", $headContent);

        foreach ($banners['banners'] as $placeName => $place) {
            $headContent =
                '
<?php if (preg_match(\'%s\', $userAgent)): ?>
%s
<?php else: ?>
%s
<?php endif; ?>
';

            $headContent = sprintf(
                $headContent,
                $place['regexp']['regexp'],
                $place['regexp']['head'],
                $place['default']['head']
            );
            file_put_contents($this->config->application->bannersDir . "head.php", $headContent, FILE_APPEND);
        }

        $allFile = scandir($this->config->application->bannersDir);
        unset($allFile[array_search('.', $allFile)], $allFile[array_search('..', $allFile)], $allFile[array_search('head.php', $allFile)]);
        $allBanners = array_map(function($file) {
            $banner = str_replace('.php', '', $file);
            return $banner;
        }, $allFile);
        $updatedBanners = array_keys($banners['banners']);
        $bannersForDelete = array_diff($allBanners, $updatedBanners);
        foreach ($bannersForDelete as $bannerForDelete) {
            unlink($this->config->application->bannersDir . "$bannerForDelete.php");
        }
    }

    protected function createContent($data)
    {
        /** @var Contents $oldContent */
        $oldContent = Contents::findFirst(
            [
                'url = :url: AND state = :state: AND type = :type:',
                'bind' => [
                    'url' => !empty($data['url']) ? $data['url'] : ' ',
                    'state' => Contents::UPDATING,
                    'type' => $this->typeMap[$data['type']]
                ]
            ]
        );

        if ($oldContent) {
            $oldContentId = $oldContent->id;
            $this->log->error("Дубль. Контент с id: $oldContentId существует");
            return;
        }

        $decodedContent = !empty($data['content'][1]) ? json_decode($data['content'][1], true) : null;

        $content = new Contents();
        $content->url = !empty($data['url']) ? $data['url'] : ' ';
        $content->controller = !empty($decodedContent['controller']) ? $decodedContent['controller'] : ' ';
        $content->action = !empty($decodedContent['action']) ? $decodedContent['action'] : ' ';
        $content->content = !empty($data['content'][1]) ? $data['content'][1] : ' ';
        $content->type = $this->typeMap[$data['type']];
        $content->state = Contents::UPDATING;
        $content->version = 2;

        if (!$content->create()) {
            $messages = $content->getMessages();
            foreach ($messages as $message) {
                $this->log->error($message->getMessage());
            }
        }
    }

    protected function deleteContents()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find(
            [
                'state = :state: AND (type = :type1: OR type = :type2:) AND action = :action:',
                'bind' => [
                    'state'  => Urls::OPEN,
                    'type1'   => Urls::CONTENT,
                    'type2'  => Urls::COMMON,
                    'action' => Urls::FOR_DELETING
                ]
            ]
        );

        foreach ($urls as $url) {
            /** @var Contents $contentForDeleting */
            $contentForDeleting = Contents::findFirst(
                [
                    'url = :url: AND type = :type:',
                    'bind' => [
                        'url'  => $url->url,
                        'type' => $url->type
                    ]
                ]
            );

            if ($contentForDeleting) {
                $contentForDeleting->delete();
                $this->log->info("Удален контент url=" . $contentForDeleting->url);
            }
            $url->delete();
        }
    }

    protected function deleteImages()
    {
        /** @var Urls[] $urls */
        $urls = Urls::find(
            [
                'state = :state: AND type = :type: AND action = :action:',
                'bind' => [
                    'state'  => Urls::OPEN,
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