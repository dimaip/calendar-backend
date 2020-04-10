<?php


class TestIterator implements Iterator
{
    private $position = 0;
    private $returnKeys;
    private $array;

    public function __construct($data, $returnKeys)
    {
        $this->position = 0;
        $this->array = $data;
        $this->returnKeys = $returnKeys;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return array_map(
            function($item) {
                return $this->array[$this->position][$item];
            },
            $this->returnKeys
        );
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->array[$this->position]);
    }
}