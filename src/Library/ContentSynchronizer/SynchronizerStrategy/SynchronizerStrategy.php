<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy;

use Client\Models\Contents;
use Client\Models\Urls;
use Phalcon\Di\Injectable;

abstract class SynchronizerStrategy extends Injectable
{
    abstract public function updateContent();
    abstract public function updateBanner();

    protected function deleteOldVersion($all = true, $startUpdatingTime)
    {
        if ($all) {
            $this->log->info('Начали удаление старой версии');
            $this->db->execute("DELETE FROM contents WHERE created_at < ?", [$startUpdatingTime]);
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

    protected function createUrl($url, $type = Urls::CONTENT, $action = Urls::FOR_UPDATING)
    {
        $urls         = new Urls();
        $urls->url    = $url;
        $urls->state  = Urls::OPEN;
        $urls->type   = $type;
        $urls->action = $action;

        try {
            if ($urls->create()) {
                return true;
            }

            $messages = $urls->getMessages();
            foreach ($messages as $message) {
                $this->log->error($message->getMessage());
            }

            return false;
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());

            return false;
        }
    }

    protected function createBanner($banners)
    {
        foreach ($banners['banners'] as $placeName => $place) {
            if (isset($place['regexp'])) {
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
                    $place['regexp']['body'] ?: '',
                    $place['default']['body'] ?: ''
                );
            } else {
                $bodyContent = $place['default']['body'] ?: '';
            }

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
            if (isset($place['regexp'])) {
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
                    $place['regexp']['head'] ?: '',
                    $place['default']['head'] ?: ''
                );
            } else {
                $headContent = $place['default']['head'] ?: '';
            }

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
}