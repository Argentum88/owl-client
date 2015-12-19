<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy;

use Client\Models\Contents;
use Client\Models\Urls;
use Phalcon\Di\Injectable;

abstract class SynchronizerStrategy extends Injectable
{
    abstract public function updateContent();
    abstract public function updateBanner();

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

    protected function nginxCacheClear()
    {
        if (!empty($this->config->ngixCacheClearScript)) {
            $this->log->info('Начали удаление кэша nginx');
            exec($this->config->ngixCacheClearScript . ' full');
            exec($this->config->ngixCacheClearScript . ' full');
            $this->log->info('Удалили кэш nginx');
        }
    }
}