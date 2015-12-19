<?php

namespace Client\Library;

use Phalcon\Di\Injectable;

class Bulk extends Injectable
{
    protected $buffer = [];

    protected $table;

    protected $columns = [];

    protected $bufferSize;

    public function __construct($table, array $columns, $bufferSize = 1000)
    {
        $this->table = $table;
        $this->columns = $columns;
        $this->bufferSize = $bufferSize;
    }

    public function insert(array $data)
    {
        $row = '(';
        foreach ($data as $item) {
            $row .= "'$item', ";
        }
        $row = rtrim($row, ', ');
        $row .= ')';

        $this->push($row);
    }

    protected function push($row)
    {
        $this->buffer[] = $row;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flash();
        }
    }

    public function flash()
    {
        $columns = implode(', ', $this->columns);
        $values = implode(', ', $this->buffer);
        $this->db->execute("INSERT INTO {$this->table} ($columns) VALUES $values");

        $this->buffer = [];

        $this->log->info('применили пачку');
    }
}