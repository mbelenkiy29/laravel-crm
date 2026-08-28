<?php

return [
    [
        'key' => 'settings.funders',
        'name' => 'funder::app.menu.funders',
        'info' => 'funder::app.menu.manage-funders',
        'route' => 'admin.settings.funders.index',
        'sort' => 10,
        'icon-class' => 'icon-settings',
    ], [
        'key' => 'settings.funders.funders',
        'name' => 'funder::app.menu.manage-funders',
        'info' => 'funder::app.menu.manage-funders',
        'route' => 'admin.settings.funders.index',
        'sort' => 1,
        'icon-class' => 'icon-settings',
    ],
];
