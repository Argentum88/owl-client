<?php

namespace Client\Models;

use Phalcon\Mvc\Model;

abstract class Launchers extends Model
{
    const UPDATE_CONTENT = 1;

    public $id;

    public $task;

    public $index;

    abstract public function launch();

    protected function actualizeLaunchIndex()
    {
        $countExistingLaunchers =  self::count("task = {$this->task}");

        if ($countExistingLaunchers >= 2 && $this->index === null) {
            throw new \Exception("Launcher'ов больше одного");
        } elseif ($countExistingLaunchers == 1) {
            $this->index = 2;
            $this->save();
        } elseif ($countExistingLaunchers == 0 || ($countExistingLaunchers == 1 && $this->index == 2)) {
            $this->index = 1;
            $this->save();
        }
    }
}
