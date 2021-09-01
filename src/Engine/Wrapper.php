<?php

namespace NextDatatable\Datatable\Engine;

use Illuminate\Support\Facades\DB;
use NextDatatable\Datatable\Datatable;

class Wrapper
{   
    private $log = [
        'queries' => [],
    ];

    /**
     * eloquent
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    private $eloquent;
    
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
     * build
     *
     * @var array
     */
    private $build = [
        'result' => [],
        'error' => [],
    ];

    /**
     * __construct
     *
     * @param  \Illuminate\Database\Eloquent\Builder $eloquent
     * @param  array $meta
     * @return void
     */
    public function __construct($eloquent, $meta)
    {
        DB::listen(function ($query) {
            $this->log['queries'][] = (object) [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ];
        });
        $this->eloquent = $eloquent;
        $this->setMeta($meta);
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
    
    
    /**
     * make
     *
     * @param  mixed $returnArray
     * @return Wrapper
     */
    public function make($returnArray = false): Wrapper
    {
        if (!$this->eloquent) {
            throw new \Exception('You must call of() method before make() method');
        }

        try {
            $result = $this->buildQuery($returnArray);
            $this->build['result'] = $result;
            $this->build['error'] = null;
        } catch (\Throwable $th) {
            throw $th;
            $returns = [
                "status" => false,
                "error" => $th->getMessage(),
            ];
            if (config('app.debug') == true) {
                $returns['queries'] = $this->log['queries'];
                $returns['params'] = $_GET;
                $returns['body'] = $_POST;
            }
            $this->build['result'] = null;
            $this->build['error'] = $returns;
        }
        return $this;
    }

    
    /**
     * buildResponse
     *
     * @param  mixed $content
     * @param  mixed $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function json($status = 200, $headers = []): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->getResult(), $status, $headers);
    }

    
    /**
     * buildQuery
     *
     * @param  mixed $returnArray
     * @return void
     */
    private function buildQuery($returnArray)
    {
        $query = clone $this->eloquent;
        $data = [];

        // get total records
        $recordsTotal = $query->count();

        //
        $columns = $this->columns;
        $order = $this->order;
        $filters = $this->filters;
        $meta = [
            'columns' => $columns,
            'order' => $order,
            'filters' => $filters,
        ];

        // build select
        $columnSelected = [];
        if (count($columns) > 0) {
            for ($i = 0; $i < count($columns); $i++)
            {
                $column = $columns[$i];
                array_push($columnSelected, $column->name);
            }
        } else {
            $columnSelected = ['*'];
        }
        $query->select($columnSelected);

        // build filters search
        if (isset($filters) && is_object($filters))
        {
            if (isset($filters->search) && $filters->search)
            {
                $search = $filters->search;
                if ($search != '')
                {
                    $query->where(function ($query) use ($columns, $search) {
                        for ($i = 0; $i < count($columns); $i++)
                        {
                            $column = $columns[$i];
                            if ($column->searchable) $query->orWhere($column->name, 'like', '%' . $search . '%');
                        }
                    });
                }
            }
        }

        // build order
        $primaryKey = $this->eloquent->getModel()->getKeyName();
        if (count($order) == 0) array_push($order, (object) ['name' => $primaryKey, 'direction' => 'asc']);
        for ($i = 0; $i < count($order); $i++)
        {
            $orderItem = (is_object($order[$i])) ? $order[$i] : (object) $order[$i];
            $query->orderBy($orderItem->name, $orderItem->direction);
        }

        // get filtered records
        $data = $query->get();
        $recordsFiltered = count($data);

        // 
        $returns = [
            "status" => true,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'meta' => $meta,
            'data' => ($returnArray) ? $data->toArray() : $data,
        ];
        if (config('datatable.debug') == true) $returns['queries'] = $this->log['queries'];

        // dd($returns);
        return $returns;
    }
    
    /**
     * getResult
     *
     * @return array
     */
    private function getResult(): array
    {
        $build = $this->build;
        if ($build['error'] != null)
        {
            return $build['error'];
        } 
        return $build['result'];
    }
    
    /**
     * __serialize
     *
     * @return array
     */
    public function __serialize(): array
    {
        return $this->getResult();        
    }

    public function __toString()
    {
        return $this->json();
    }
}
