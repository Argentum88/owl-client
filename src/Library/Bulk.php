<?php

namespace Client\Library;

use Phalcon\Di\Injectable;

class Bulk extends Injectable
{
    protected $placeholderBuffer = [];

    protected $rowBuffer = [];

    protected $table;

    protected $columns = [];

    protected $bufferSize;

    public function __construct($table, array $columns, $bufferSize = 1000)
    {
        $this->table = $table;
        $this->columns = $columns;
        $this->bufferSize = $bufferSize;
    }

    public function insert(array $row)
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
        $columns = implode(', ', $this->columns);
        $placeholders = implode(', ', $this->placeholderBuffer);

        try {
            $this->db->execute("INSERT INTO {$this->table} ($columns) VALUES $placeholders", $this->rowBuffer);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
        }

        $this->placeholderBuffer = $this->rowBuffer = [];

        $this->log->info('применили пачку');
    }
}