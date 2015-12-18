<?php

namespace Client\Tasks;

use Client\Library\ContentSynchronizer\SynchronizerStrategy\FileStrategy\FileStrategy;
use Client\Models\Events;
use Phalcon\CLI\Task;
use Client\Library\ContentSynchronizer\ContentSynchronizer;
use Client\Library\ContentSynchronizer\SynchronizerStrategy\ScraperStrategy\ScraperStrategy;


class SyncTask extends Task
{
    public function updateContentViaScraperAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->updateContent();
    }

    public function updateContentViaFileAction($params)
    {
        $file = $params[3];
        $fullUpdate = $params[4];
        (new ContentSynchronizer(new FileStrategy($file, $fullUpdate)))->updateContent();
    }

    public function updateBannerViaScraperAction()
    {
        (new ContentSynchronizer(new ScraperStrategy()))->updateBanner();
    }

    public function updateBannerViaFileAction($params)
    {
        $file = $params[3];
        (new ContentSynchronizer(new FileStrategy($file)))->updateBanner();
    }

    public function putWatermarkAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->putWatermark();
    }

    public function replaceWatermarkAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->replaceWatermark();
    }

    public function fetchExistingImagesAction()
    {
        (new ContentSynchronizer(new FileStrategy()))->fetchExistingImages();
    }

    public function updateBannerAction()
    {
        /** @var Events $event */
        $event = Events::findFirst([
            'type = :type: AND state = :state:',
            'bind' => [
                'type'  => Events::UPDATE_BANNER,
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ($event) {
            return $event->processUpdateBanner($this);
        }

        return false;
    }

    public function updateContentAction()
    {
        /** @var Events $event */
        $event = Events::findFirst([
            '(type = :type1: OR type = :type2:) AND state = :state:',
            'bind' => [
                'type1' => Events::UPDATE_CONTENT,
                'type2' => Events::FULL_UPDATE_CONTENT,
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ($event) {
            return $event->processUpdateContent($this);
        }

        return false;
    }

    public function cacheImageAction()
    {
        /** @var Events $event */
        $event = Events::findFirst([
            '(type = :type1: OR type = :type2:) AND state = :state:',
            'bind' => [
                'type1' => Events::CACHE_IMAGE_AFTER_PARTIAL_UPDATE,
                'type2' => Events::CACHE_IMAGE_AFTER_FULL_UPDATE,
                'state' => Events::OPEN,
            ],
            'order' => 'created_at ASC'
        ]);

        if ($event) {
            return $event->processCacheImage($this);
        }

        return false;
    }
}