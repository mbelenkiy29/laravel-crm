<?php

return [
    [
        'key' => 'settings.funders',
        'name' => 'funder::app.acl.funders',
        'route' => 'admin.settings.funders.index',
        'sort' => 10,
    ], [
        'key' => 'settings.funders.funders',
        'name' => 'funder::app.acl.manage-funders',
        'route' => 'admin.settings.funders.index',
        'sort' => 1,
    ], [
        'key' => 'settings.funders.funders.create',
        'name' => 'funder::app.acl.create',
        'route' => 'admin.settings.funders.store',
        'sort' => 1,
    ], [
        'key' => 'settings.funders.funders.edit',
        'name' => 'funder::app.acl.edit',
        'route' => ['admin.settings.funders.edit', 'admin.settings.funders.update'],
        'sort' => 2,
    ], [
        'key' => 'settings.funders.funders.delete',
        'name' => 'funder::app.acl.delete',
        'route' => 'admin.settings.funders.delete',
        'sort' => 3,
    ],
];
