<?php

namespace Webkul\Funder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Webkul\Funder\Repositories\FunderRepository;
use Webkul\Funder\Services\SubmitToFunders;
use Webkul\Lead\Repositories\LeadRepository;

class LeadSubmitController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected FunderRepository $funderRepository,
        protected SubmitToFunders $submitToFunders,
    ) {}

    /**
     * Submit a lead to one or more funders. Always JSON 200 when some destinations error.
     */
    public function store(int $id): JsonResponse
    {
        $this->validate(request(), [
            'funder_ids' => 'required|array',
            'funder_ids.*' => 'integer',
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        $ids = array_map('intval', request()->input('funder_ids', []));

        $funders = $ids === []
            ? collect()
            : collect($this->funderRepository->findWhereIn('id', $ids))->keyBy('id');

        $ordered = collect($ids)
            ->map(fn (int $funderId) => $funders->get($funderId))
            ->filter();

        $submissions = $this->submitToFunders->submit(
            $lead,
            $ordered,
            request()->input('docs', []),
        );

        return new JsonResponse([
            'data' => $submissions,
            'message' => trans('funder::app.leads.submit-success'),
        ]);
    }
}
