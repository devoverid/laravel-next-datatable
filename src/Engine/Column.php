<?php

namespace NextDatatable\Datatable\Engine;

class Column
{
    private $raw;
    private $column;
    public $name;
    public $label;
    
    /**
     * __construct
     *
     * @param  mixed $column
     * @return void
     */
    public function __construct($column)
    {
        $this->initColumn($column);
        $this->name = $this->fill('name', '');
        $this->label = $this->fill('label', '');
        $this->searchable = $this->fill('searchable', true);
        $this->sortable = $this->fill('sortable', true);
    }

    /**
     * initColumn
     * 
     * @param  mixed $column
     * @return void
     */
    private function initColumn($column)
    {
        // 
        if (is_string($column)) $column = json_decode($column);

        // 
        $this->raw = $column;
        $this->column = $column;
        if (is_array($column)) $column = (object) $column;
    }
    
    /**
     * fill
     *
     * @param  mixed $key
     * @param  mixed $default
     * @return mixed
     */
    private function fill($key, $default): mixed
    {
        return isset($this->column->{$key}) ? $this->column->{$key} : $default;
    }
}
