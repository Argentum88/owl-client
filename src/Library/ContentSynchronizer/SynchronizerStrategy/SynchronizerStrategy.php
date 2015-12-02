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

        /** @var Contents[] $contents */
        $contents = Contents::find([
                'created_at >= :created_at:',
                'bind' => [
                    'created_at' => $startUpdatingTime
                ]
            ]);

        $count = 0;
        $this->db->begin();
        foreach ($contents as $content) {
            if ($count > 100) {
                $this->db->commit();
                $this->db->begin();
                $count = 0;
                $this->log->info('применили транзакцию удаления старой версии');
            }

            $oldContents = Contents::find([
                    'url = :url: AND type = :type: AND created_at < :created_at:',
                    'bind' => [
                        'url' => $content->url,
                        'type' => $content->type,
                        'created_at' => $startUpdatingTime
                    ]
                ]);

            $oldContents->delete();
            $count++;
        }
        $this->db->commit();

        $this->log->info('Удалена старая версия');
    }

    protected function createUrl($url, $type = Urls::CONTENT, $action = Urls::FOR_UPDATING)
    {
        /** @var Urls $oldUrls */
        $oldUrls = Urls::findFirst(
            [
                'url = :url: AND type = :type: AND action = :action:',
                'bind' => [
                    'url' => $url,
                    'type' => $type,
                    'action' => $action
                ]
            ]
        );

        if ($oldUrls) {
            $oldUrlsId = $oldUrls->id;
            $this->log->error("Дубль: Урл с id: $oldUrlsId существует");
            return false;
        }

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
}