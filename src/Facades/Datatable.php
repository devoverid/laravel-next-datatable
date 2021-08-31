<?php

namespace NextDatatable\Datatable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \NextDatatable\Datatable\Skeleton\SkeletonClass
 */
class Datatable extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'datatable';
    }
}
