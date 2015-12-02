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