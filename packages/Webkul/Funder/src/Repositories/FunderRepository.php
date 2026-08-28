<?php

namespace Webkul\Funder\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Funder\Contracts\Funder;

class FunderRepository extends Repository
{
    /**
     * Searchable fields
     */
    protected $fieldSearchable = [
        'name',
        'kind',
        'route',
    ];

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return Funder::class;
    }
}
