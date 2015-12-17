<?php

namespace Client\Models;

class Events extends \Phalcon\Mvc\Model
{
    const FULL_UPDATE_CONTENT  = 8;
    const UPDATE_CONTENT  = 1;
    const UPDATE_BANNER = 2;

    const OPEN = 3;
    const CONTENT_UPDATING = 4;
    const IMAGE_UPDATING = 5;
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
            file_put_contents($compressedFile, fopen($this->getDI()->get('config')->owl . $patch, 'r'));
        } else {
            $this->getDI()->get('log')->error("не удалось открыть файл " . $this->getDI()->get('config')->owl . $patch);
            $this->state = self::ERROR;
            $this->save();
            throw new \Exception();
        }

        exec("bzip2 -d $compressedFile");
        $uncompressedFile = $this->getDI()->get('config')->tempDir . "/$uniqid";
        return $uncompressedFile;
    }

    /**
     * @param \Client\Tasks\SyncTask $task
     * @return bool
     */
    public function processUpdateContent($task)
    {
        $event = self::findFirst([
            '(type = :type1: OR type = :type2:) AND (state = :state:)',
            'bind' => [
                'type1'  => self::UPDATE_CONTENT,
                'type2'  => self::FULL_UPDATE_CONTENT,
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

        $imageUpdatingEvent = self::findFirst(
            [
                'state = :state:',
                'bind' => [
                    'state' => self::IMAGE_UPDATING,
                ]
            ]
        );

        if (!$imageUpdatingEvent) {

            $this->state = self::IMAGE_UPDATING;
            $this->save();

            $task->putWatermarkAction();
        }

        $this->state = self::DONE;
        $this->save();

        return true;
    }

    /**
     * @param \Client\Tasks\SyncTask $task
     * @return bool
     */
    public function processUpdateBanner($task)
    {
        $this->getDI()->get('log')->info('начали синхронизацию баннеров');

        $file = $this->getFile();
        $task->updateBannerViaFileAction([3 => $file]);
        unlink($file);

        $this->getDI()->get('log')->info('закончили синхронизацию баннеров');
        $this->state = self::DONE;
        $this->save();

        return true;
    }
}
