<?php

namespace Client\Models;

use Phalcon\Mvc\Model;

abstract class Launchers extends Model
{
    const PRIMARY = 1;
    const SECONDARY = 2;
    const UPDATE_CONTENT = 3;

    public $id;

    public $task;

    public $type;

    abstract protected function launchTask();

    public function start()
    {
        $startTime = time();

        $this->actualizeLaunchType();
        while (time() < ($startTime + 58)) {
            if ($this->type == self::PRIMARY) {

                try {
                    $this->launchTask();
                } catch (\Exception $e) {
                    $this->log->error($e->getMessage());
                }
            }

            $this->actualizeLaunchType();
            sleep(1);
        }

        $this->delete();
    }

    protected function actualizeLaunchType()
    {
        $primaryLauncher =  self::findFirst("type = " . self::PRIMARY);
        $secondaryLauncher =  self::findFirst("type = " . self::SECONDARY);

        $launcherIsStarted = !empty($this->type) ? true : false;

        if ($secondaryLauncher && !$launcherIsStarted) {
            throw new \Exception("Launcher'ов больше не надо");
        } elseif ($primaryLauncher && !$launcherIsStarted) {
            $this->type = self::SECONDARY;
            $this->save();
        } elseif ((!$primaryLauncher && !$secondaryLauncher) || (!$primaryLauncher && $secondaryLauncher)) {
            $this->type = self::PRIMARY;
            $this->save();
        }
    }
}
