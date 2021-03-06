<?php

namespace Client\Models;

class Bulk extends \Phalcon\Mvc\Model
{
    protected $placeholderBuffer = [];

    protected $rowBuffer = [];

    protected $table;

    protected $columns = [];

    protected $bufferSize = 1000;

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function setColumns(array $columns)
    {
        $this->columns = $columns;
    }

    public function setBufferSize($bufferSize)
    {
        $this->bufferSize = $bufferSize;
    }

    public function init($table, array $columns, $bufferSize = 1000)
    {
        $this->setTable($table);
        $this->setColumns($columns);
        $this->setBufferSize($bufferSize);
    }

    public function insert($row)
    {
        $placeholder = '(' . rtrim(str_repeat('?,', count($row)), ',') . ')';

        $this->putInBuffers($placeholder, $row);
    }

    protected function putInBuffers($placeholder, $row)
    {
        $this->placeholderBuffer[] = $placeholder;

        foreach ($row as $item) {
            $this->rowBuffer[] = $item;
        }

        if (count($this->placeholderBuffer) >= $this->bufferSize) {
            $this->flash();
        }
    }

    public function flash()
    {
        if (count($this->placeholderBuffer) == 0) {
            return;
        }

        $columns = implode(', ', $this->columns);
        $placeholders = implode(', ', $this->placeholderBuffer);

        try {
            $this->getDI()->get('db')->execute("INSERT INTO {$this->table} ($columns) VALUES $placeholders", $this->rowBuffer);
        } catch (\Exception $e) {
            $this->getDI()->get('log')->error($e->getMessage());
        }

        $this->placeholderBuffer = $this->rowBuffer = [];

        $this->getDI()->get('log')->info('применили пачку');
    }
}