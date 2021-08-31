<?php

namespace NextDatatable\Datatable\Engine;

class Wrapper
{   
    
    /**
     * Raw data from the meta
     *
     * @var array
     */
    private $rawMeta = [];

    /**
     * columns
     *
     * @var array
     */
    public $columns = [];    
    
    /**
     * order
     *
     * @var array
     */
    public $order = []; 
    
    /**
     * filters
     *
     * @var array
     */
    public $filters;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function setMeta($meta = []): void
    {
        $this->meta     = $meta;
        $this->columns  = $this->initColumns($this->getMeta('columns', []));
        $this->order    = $this->getMeta('order', []);
        $this->filters  = (object) $this->getMeta('filters', []);
    }
    
    /**
     * Get meta from request
     *
     * @param  mixed $key
     * @param  mixed $default
     * @return void
     */
    private function getMeta($key, $default = null)
    {
        return isset($this->meta[$key]) ? $this->meta[$key] : $default;
    }
    
    /**e
     * Init Columns
     *
     * @return void
     */
    private function initColumns($columns): array
    {
        $result = [];
        foreach ($columns as $column)
        {
            array_push($result, new Column($column));
        }
        return $result;
    }
}
