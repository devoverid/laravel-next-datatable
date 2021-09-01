<?php

namespace NextDatatable\Datatable;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NextDatatable\Datatable\Engine\Wrapper;
use NextDatatable\Datatable\Engine\Column;

class Datatable
{
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
        $eloquent = clone $this->eloquent;
        $meta = $this->initMeta();

        $wrapper = $this->initWrapper($eloquent, $meta);
        $content = $wrapper->make($returnArray);

        return $content;
    }
    
    /**
     * initWrapper
     *
     * @param  \Illuminate\Database\Eloquent\Builder $eloquent
     * @param  array $meta
     * @return Wrapper
     */
    private function initWrapper($eloquent, $meta)
    {
        return new Wrapper($eloquent, $meta);
    }
       
    /**
     * Init meta
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
        return $meta;
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
