<?php

use Webkul\Lead\Models\Lead;

it('returns the documented broker pipeline stage codes and kanban groups', function () {
    $stages = Lead::pipelineStages();
    $codes = array_column($stages, 'code');

    $expected = [
        'LEAD',
        'NEW_APPLICATION',
        'MISSING_DOCUMENTS',
        'READY_TO_SUBMIT',
        'CLOSED_UNABLE_TO_SUBMIT',
        'CLOSED_MISSING_DOCUMENTS',
        'SUBMITTED',
        'RECEIVED_DLVC',
        'OFFER_SELECTED',
        'OFFER_PITCHED',
        'OFFER_ACCEPTED',
        'APPROVED',
        'CONTRACTS_REQUESTED',
        'CLOSED_DECLINED',
        'CLOSED_OFFER_REJECTED',
        'REPRICING',
        'CONTRACTS_SENT',
        'FUNDED_DEFAULTED',
        'FINAL_REVIEW',
        'CLOSED_KILLED_BY_FUNDER',
        'CONTRACTS_SIGNED',
        'FUNDED',
        'FUNDED_UP_FOR_RENEWAL',
        'FUNDED_MISSED_PAYMENTS',
        'FUNDED_RENEWED',
        'RESUBMITTING',
        'CLOSED_UNRESPONSIVE',
    ];

    expect($codes)->toBe($expected);
    expect($codes)->not->toContain('Contract Out');
    expect($codes)->not->toContain('CONTRACT_OUT');

    $byCode = [];

    foreach ($stages as $stage) {
        $byCode[$stage['code']] = $stage;
    }

    expect($byCode['LEAD']['kanban_group'])->toBe('lead');
    expect($byCode['NEW_APPLICATION']['kanban_group'])->toBe('new');
    expect($byCode['MISSING_DOCUMENTS']['kanban_group'])->toBe('new');
    expect($byCode['READY_TO_SUBMIT']['kanban_group'])->toBe('new');
    expect($byCode['SUBMITTED']['kanban_group'])->toBe('submitted');
    expect($byCode['RECEIVED_DLVC']['kanban_group'])->toBe('submitted');
    expect($byCode['RESUBMITTING']['kanban_group'])->toBe('submitted');
    expect($byCode['APPROVED']['kanban_group'])->toBe('offers');
    expect($byCode['OFFER_SELECTED']['kanban_group'])->toBe('offers');
    expect($byCode['OFFER_PITCHED']['kanban_group'])->toBe('offers');
    expect($byCode['OFFER_ACCEPTED']['kanban_group'])->toBe('offers');
    expect($byCode['REPRICING']['kanban_group'])->toBe('offers');
    expect($byCode['FINAL_REVIEW']['kanban_group'])->toBe('offers');
    expect($byCode['CONTRACTS_REQUESTED']['kanban_group'])->toBe('contracts');
    expect($byCode['CONTRACTS_SENT']['kanban_group'])->toBe('contracts');
    expect($byCode['CONTRACTS_SIGNED']['kanban_group'])->toBe('contracts');
    expect($byCode['FUNDED']['kanban_group'])->toBe('funded');
    expect($byCode['FUNDED_UP_FOR_RENEWAL']['kanban_group'])->toBe('funded');
    expect($byCode['FUNDED_MISSED_PAYMENTS']['kanban_group'])->toBe('funded');
    expect($byCode['FUNDED_RENEWED']['kanban_group'])->toBe('funded');
    expect($byCode['FUNDED_DEFAULTED']['kanban_group'])->toBe('funded');

    foreach ($stages as $stage) {
        if (str_starts_with($stage['code'], 'CLOSED_')) {
            expect($stage['kanban_group'])->toBe('closed');
        }

        expect($stage)->toHaveKeys(['code', 'name', 'kanban_group', 'kanban_group_label', 'sort_order']);
    }

    $groupCodes = array_column(Lead::kanbanGroups(), 'code');

    expect($groupCodes)->toBe([
        'lead',
        'new',
        'submitted',
        'offers',
        'contracts',
        'funded',
        'closed',
    ]);
});

it('does not add a deals table migration', function () {
    $root = dirname(__DIR__, 2);
    $files = glob($root.'/packages/Webkul/*/src/Database/Migrations/*.php') ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toContain("Schema::create('deals'");
        expect($contents)->not->toContain('create_deals_table');
        expect(basename($file))->not->toContain('deals');
    }
});
