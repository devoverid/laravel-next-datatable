<?php

namespace NextDatatable\Datatable;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NextDatatable\Datatable\Engine\Wrapper;
use NextDatatable\Datatable\Engine\Column;

class Datatable
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
     * request
     *
     * @var Request
     */
    private $request;

        
    /**
     * wrapper
     *
     * @var Wrapper
     */
    private $wrapper;
    
    /**
     * http_method
     *
     * @var string
     */
    private $http_method = 'GET';
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    
    /**
     * Add Query to log
     *
     * @param  mixed $query
     * @return void
     */
    public function addQueryLog($query)
    {
        $this->log['queries'][] = $query;
    }
    
    /**
     * Pass the eloquent builder to the datatable
     * @param  \Illuminate\Database\Eloquent\Builder $eloquent
     * @return void
     */
    public function of($eloquent)
    {
        if (!$eloquent instanceof \Illuminate\Database\Eloquent\Builder) {
            throw new \Exception('The first parameter must be an instance of Illuminate\Database\Eloquent\Builder');
        }
        $this->eloquent = $eloquent;
        return $this;
    }
    
    /**
     * Make datatable
     * 
     * @return void
     */
    public function make($returnArray = true)
    {
        if (!$this->eloquent) {
            throw new \Exception('You must call of() method before make() method');
        }

        // reset log
        $this->log = [
            'queries' => [],
        ];
    
        try {
            // 
            $this->initWrapper();
            $this->initMeta();

            $result = $this->buildQuery($returnArray);
            return $this->buildResponse($result);
        } catch (\Throwable $th) {
            $returns = [
                "status" => false,
                "error" => $th->getMessage(),
            ];
            if (config('app.debug') == true) {
                $returns['queries'] = $this->log['queries'];
                $returns['request'] = $this->request->all();
            }
            return $returns;
        }
    }

    private function buildResponse($content, $status = 200)
    {
        $headers = [];
        return response()->json($content, $status, $headers);
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
        $columns = $this->wrapper->columns;
        $order = $this->wrapper->order;
        $filters = $this->wrapper->filters;
        $meta = [
            'columns' => $columns,
            'order' => $order,
            'filters' => $filters,
        ];

        // build select
        $columnSelected = [];
        for ($i = 0; $i < count($columns); $i++)
        {
            $column = $columns[$i];
            array_push($columnSelected, $column->name);
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
     * init wrapper
     *
     * @return void
     */
    private function initWrapper()
    {
        $this->wrapper = new Wrapper();
    }
    
    /**
     * init meta
     *
     * @return void
     */
    private function initMeta()
    {
        $request = $this->request;
        if (!$request instanceof Request) {
            throw new \Exception('The first parameter must be an instance of Illuminate\Http\Request');
        }

        $this->http_method = $request->getMethod();
        $meta = [
            'columns' => $this->get('columns', []),
            'order' => $this->get('order', []),
            'filters' => (object) $this->get('filters', []),
        ];
        $this->wrapper->setMeta($meta);
    }
    
    /**
     * Get request value
     *
     * @param  mixed $key
     * @param  mixed $default
     * @return void
     */
    private function get($key, $default = null)
    {
        $value = ($this->http_method == 'GET')
            ? $this->request->get($key, $default)
            : $this->request->input($key, $default);
        
        if (is_string($value)) {
            try {
                $value = json_decode($value);
            } catch (\Exception $e) {
            }
        }
        
        return $value;
    }
}
