<?php

namespace Webkul\Funder\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class FunderDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('funders')
            ->addSelect(
                'funders.id',
                'funders.name',
                'funders.kind',
                'funders.route'
            );

        $this->addFilter('id', 'funders.id');
        $this->addFilter('name', 'funders.name');
        $this->addFilter('kind', 'funders.kind');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('funder::app.settings.funders.index.datagrid.id'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('funder::app.settings.funders.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'kind',
            'label' => trans('funder::app.settings.funders.index.datagrid.kind'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'route',
            'label' => trans('funder::app.settings.funders.index.datagrid.route'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);
    }

    /**
     * Prepare Actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.funders.funders.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('funder::app.acl.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.settings.funders.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.funders.funders.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('funder::app.acl.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.settings.funders.delete', $row->id),
            ]);
        }
    }
}
