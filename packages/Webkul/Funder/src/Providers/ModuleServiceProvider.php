<?php

namespace Webkul\Funder\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Funder\Models\Funder;
use Webkul\Funder\Models\Submission;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Funder::class,
        Submission::class,
    ];
}
