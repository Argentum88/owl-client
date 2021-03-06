<?php

namespace Client\Models;

class Events extends \Phalcon\Mvc\Model
{
    const FULL_UPDATE_CONTENT  = 8;
    const UPDATE_CONTENT  = 1;
    const UPDATE_BANNER = 2;
    const CACHE_IMAGE_AFTER_PARTIAL_UPDATE = 9;
    const CACHE_IMAGE_AFTER_FULL_UPDATE = 11;

    const OPEN = 3;
    const CONTENT_UPDATING = 4;
    const BANNER_UPDATING = 10;
    const IMAGE_CACHING = 5;
    const DONE = 6;
    const ERROR = 7;

    public $id;

    public $type;

    public $state;

    public $data;

    public $created_at;

    public function beforeCreate()
    {
        $this->created_at = date(DATE_ISO8601);
    }

    protected function getFile()
    {
        $eventData = json_decode($this->data, true);
        $patch = $eventData['patch'];
        $uniqid = uniqid();
        $compressedFile = $this->getDI()->get('config')->tempDir . "/$uniqid.bz2";

        $handle = fopen($this->getDI()->get('config')->owl . $patch, 'r');
        if ($handle) {
            file_put_contents($compressedFile, $handle);
        } else {
            $this->getDI()->get('log')->error("не удалось открыть файл " . $this->getDI()->get('config')->owl . $patch);
            $this->state = self::ERROR;
            $this->save();
            throw new \Exception();
        }
        
        return $compressedFile;
    }

    /**
     * @param \Client\Tasks\SyncTask $task
     * @return bool
     */
    public function processUpdateContent($task)
    {
        $event = self::findFirst([
            'state = :state:',
            'bind' => [
                'state' => self::CONTENT_UPDATING,
            ]
        ]);

        if ($event) {
            $this->getDI()->get('log')->error("Предыдущие обновление контента не завершено");
            return false;
        }

        $this->getDI()->get('log')->info('начали синхронизацию контента');

        $this->state = self::CONTENT_UPDATING;
        $this->save();

        $file = $this->getFile();
        $fullUpdate = false;
        if ($this->type == self::FULL_UPDATE_CONTENT) {
            $fullUpdate = true;
        }
        $task->updateContentViaFileAction([3 => $file, 4 => $fullUpdate]);
        unlink($file);

        $this->getDI()->get('log')->info('закончили синхронизацию контента');

        if ($fullUpdate) {
            $this->type = self::CACHE_IMAGE_AFTER_FULL_UPDATE;
        } else {
            $this->type = self::CACHE_IMAGE_AFTER_PARTIAL_UPDATE;
        }
        $this->state = self::OPEN;
        $this->save();

        return true;
    }

    /**
     * @param \Client\Tasks\SyncTask $task
     * @return bool
     */
    public function processUpdateBanner($task)
    {
        $event = self::findFirst([
            'state = :state:',
            'bind' => [
                'state' => self::BANNER_UPDATING,
            ]
        ]);

        if ($event) {
            $this->getDI()->get('log')->error("Предыдущие обновление баннеров не завершено");
            return false;
        }

        $this->getDI()->get('log')->info('начали синхронизацию баннеров');

        $this->state = self::BANNER_UPDATING;
        $this->save();

        $file = $this->getFile();
        $task->updateBannerViaFileAction([3 => $file]);
        unlink($file);

        $this->getDI()->get('log')->info('закончили синхронизацию баннеров');
        $this->state = self::DONE;
        $this->save();

        return true;
    }

    /**
     * @param \Client\Tasks\SyncTask $task
     * @return bool
     */
    public function processCacheImage($task)
    {
        $event = self::findFirst([
            'state = :state:',
            'bind' => [
                'state' => self::IMAGE_CACHING,
            ]
        ]);

        if ($event) {
            $this->getDI()->get('log')->error("Картинки уже кэшируются");
            return false;
        }

        $this->state = self::IMAGE_CACHING;
        $this->save();

        $task->putWatermarkAction();

        $this->state = self::DONE;
        $this->save();

        return true;
    }
}
