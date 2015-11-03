<?php

namespace Client\Library\ContentSynchronizer\SynchronizerStrategy;

use Client\Models\Contents;
use Phalcon\Di\Injectable;

abstract class SynchronizerStrategy extends Injectable
{
    abstract public function initiallyFill(array $params = []);

    abstract public function fullUpdate(array $params = []);

    abstract public function update(array $params = []);

    protected function setReadyState()
    {
        $this->db->execute("UPDATE contents SET state = ? WHERE state = ?", [Contents::READY, Contents::UPDATING]);
        $this->log->info('Сброс статуса обновления');
    }

    protected function deleteFirstVersion($all = true)
    {
        if ($all) {
            $this->db->execute("DELETE FROM contents WHERE version = ?", [1]);
            $this->log->info('Удалена первая версия');

            return;
        }

        /** @var Contents[] $contents */
        $contents = Contents::find([
                'version = :version:',
                'bind' => [
                    'version' => 2
                ]
            ]);

        $count = 0;
        $this->db->begin();
        foreach ($contents as $content) {
            if ($count > 100) {
                $this->db->commit();
                $this->db->begin();
                $count = 0;
                $this->log->info('применили транзакцию удаления первой версии');
            }

            $contentOfFirstVersion  = Contents::findFirst([
                    'version = :version: AND url = :url:',
                    'bind' => [
                        'version' => 1,
                        'url' => $content->url
                    ]
                ]);

            if ($contentOfFirstVersion) {
                $contentOfFirstVersion->delete();

                $count++;
            }
        }
        $this->db->commit();

        $this->log->info('Удалена первая версия');
    }

    protected function moveSecondVersionToFirst()
    {
        $this->db->execute("UPDATE contents SET version = ? WHERE version = ?", [1, 2]);
        $this->log->info('Сброс версии');
    }
}