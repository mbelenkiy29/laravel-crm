<?php

use Illuminate\Support\Facades\Route;
use Webkul\Funder\Http\Controllers\FunderController;
use Webkul\Funder\Http\Controllers\LeadSubmitController;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(config('app.admin_path'))
    ->group(function () {
        Route::controller(FunderController::class)->prefix('settings/funders')->group(function () {
            Route::get('', 'index')->name('admin.settings.funders.index');
            Route::post('', 'store')->name('admin.settings.funders.store');
            Route::get('{id}/edit', 'edit')->name('admin.settings.funders.edit');
            Route::put('{id}', 'update')->name('admin.settings.funders.update');
            Route::delete('{id}', 'destroy')->name('admin.settings.funders.delete');
        });

        Route::post('leads/{id}/funders/submit', [LeadSubmitController::class, 'store'])
            ->name('admin.leads.funders.submit');
    });
