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
     * pagination
     *
     * @var array
     */
    public $pagination = [];    
    
    /**
     * order
     *
     * @var array
     */
    public $order = []; 
    
    /**
     * filters
     *
     * @var object
     */
    public $filters;
    
    /**
     * filter callback
     *
     * @var array
     */
    private $filterCallback = [];
    
    /**
     * costum columns
     *
     * @var array
     */
    private $customColumn = [];

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
        // load meta
        $this->meta = $meta;

        // custom
        $order = $this->getMeta('order', []);
        foreach ($order as $key => $val)
        {
            if (gettype($val) === 'string') $order[$key] = json_decode($val);
        }

        // 
        $this->columns      = $this->initColumns($this->getMeta('columns', []));
        $this->order        = $order;
        $this->filters      = (object) $this->getMeta('filters', []);
        $this->pagination   = (object) $this->getMeta('pagination', []);
    }
    
    /**
     * Get meta from request
     *
     * @param  mixed $key
     * @param  mixed $default
     * @return mixed
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
     * handle filter
     *
     * @param  mixed $name
     * @param  mixed $callback
     * @return Wrapper
     */
    public function filter($name, $callback) {
        array_push($this->filterCallback, [$name, $callback]);
        return $this;
    }
    
    /**
     * add custom column
     *
     * @param  mixed $name
     * @param  mixed $callback
     * @return Wrapper
     */
    public function addColumn($name, $callback) {
        array_push($this->customColumn, [$name, $callback]);
        return $this;
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
        $pagination = $this->pagination;
        $meta = (object) [
            'columns' => $columns,
            'order' => $order,
            'filters' => $filters,
            'pagination' => null,
        ];

        // build select
        // $columnSelected = [];
        // if (count($columns) > 0) {
        //     for ($i = 0; $i < count($columns); $i++)
        //     {
        //         $column = $columns[$i];
        //         array_push($columnSelected, $column->name);
        //     }
        // } else {
        //     $columnSelected = ['*'];
        // }
        // $query->select($columnSelected);

        // build filters search
        if (isset($filters) && is_object($filters))
        {
            if (isset($filters->search) && $filters->search)
            {
                $search = $filters->search;
                if ($search != '')
                {
                    $query->where(function ($query) use ($columns, $search) {
                        // main table
                        for ($i = 0; $i < count($columns); $i++)
                        {
                            // 
                            $column = $columns[$i];

                            // 
                            if (!$column->searchable) continue;
                            if (strpos($column->name, '.')) continue;

                            // 
                            $query->orWhere($column->name, 'like', '%' . $search . '%');
                        }
                        
                        // mapping relations
                        $relations = [];
                        for ($i = 0; $i < count($columns); $i++)
                        {
                            $column = $columns[$i];
                            if (!strpos($column->name, '.')) continue;

                            $col = explode('.', $column->name);
                            $key = $col[count($col)-1];
                            unset($col[count($col)-1]);
                            $relation = implode('.', $col);
                            
                            if (!isset($relations[$relation])) $relations[$relation] = [];
                            $relations[$relation][] = $key;
                        }

                        // apply search on relations table
                        foreach ($relations as $key => $relation )
                        {
                            $query->orWhereHas($key, function ($query) use ($relation, $search)
                            {
                                $query->where(function ($query) use ($relation, $search) {
                                    foreach ($relation as $col)
                                    {
                                        $query->orWhere($col, 'like', '%' . $search . '%');
                                    }
                                });
                                return $query;
                            });
                        }
                    });

                    // 
                    // if (strpos($column->name, '.')) 
                    // {
                    //     $cols = explode('.', $column->name);
                    //     $table_relation =  $query->getModel()->detail()->getModel()->getTable();
                    //     $query->orWhereHas($cols[0], function ($q) use ($cols, $search) {
                    //         $q->where($cols[1], 'like', '%' . $search . '%');
                    //     });
                    //     // $column->name = implode('.', $cols);
                    // } else {
                    //     $query->orWhere($column->name, 'like', '%' . $search . '%');
                    // }
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

        // custom filters
        foreach ($this->filterCallback as $callback)
        {
            $name = $callback[0];
            if (!isset($filters->{$name})) continue;
            $data = $filters->{$name};
            $query = call_user_func_array($callback[1], [$query, $data]);
        }

        // get filtered records
        $recordsFiltered = $query->count();

        // build pagination
        if (isset($pagination) && is_object($pagination))
        {
            $currentPage = $pagination->currentPage ?? 1;
            $perPage = $pagination->perPage ?? 10;
            $start_index = ($currentPage > 1) ? ($currentPage * $perPage) - $perPage : 0;
            $firstItemIndex = $start_index + 1;
            $lastItemIndex = $start_index + $perPage;
            
            $meta->pagination = (object) [
                'currentPage' => $currentPage,
                'perPage' => $perPage,
                'totalPage' => 0,
                'firstItemIndex' => $firstItemIndex,
                'lastItemIndex' => $lastItemIndex,
            ];
            $query->offset($start_index)->limit($perPage);
        }

        // get paginated records
        $data = $query->get();
        $recordsPaginated = count($data);

        // paginate
        if (isset($pagination) && is_object($pagination))
        {
            $meta->pagination->totalPage = ceil($recordsFiltered / $meta->pagination->perPage);
        }

        // add custom columns
        // $data = ($returnArray) ? $data->toArray() : $data;
        

        // custom columns
        $data_arr = $data->toArray();
        foreach ($data_arr as $record_index => $record)
        {
            foreach ($this->customColumn as $column_index => $column)
            {
                $name = $column[0];
                $callback = $column[1];
                $data_arr[$record_index][$name] = call_user_func_array($callback, [$data[$record_index], $data]);
            }
        }


        // 
        $returns = [
            "status" => true,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'meta' => $meta,
            'data' => $data_arr,
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
