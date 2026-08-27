<?php

namespace Webkul\Funder\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Funder\Contracts\Submission;

class SubmissionRepository extends Repository
{
    /**
     * Searchable fields
     */
    protected $fieldSearchable = [
        'lead_id',
        'funder_id',
        'route',
        'status',
    ];

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return Submission::class;
    }
}
