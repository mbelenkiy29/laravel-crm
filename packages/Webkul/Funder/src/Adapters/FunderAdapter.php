<?php

namespace Webkul\Funder\Adapters;

use RuntimeException;
use Webkul\Funder\Contracts\Funder as FunderContract;
use Webkul\Funder\Contracts\Submission as SubmissionContract;
use Webkul\Funder\Models\Submission;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\Quote\Models\Quote;

class FunderAdapter
{
    /**
     * Submit a lead to a funder destination.
     *
     * Only `sandbox` is implemented. Other kinds throw without creating a quote.
     *
     * @param  mixed  $docs
     */
    public function submit(LeadContract $lead, FunderContract $funder, $docs = []): SubmissionContract
    {
        if ($funder->kind !== 'sandbox') {
            throw new RuntimeException("Funder kind [{$funder->kind}] is not implemented.");
        }

        $submission = new Submission;
        $submission->lead_id = $lead->id;
        $submission->funder_id = $funder->id;
        $submission->route = $funder->route;
        $submission->status = Submission::STATUS_SENT;
        $submission->save();

        $quote = new Quote;
        $quote->subject = 'Sandbox offer from '.$funder->name;
        $quote->person_id = $lead->person_id;
        $quote->user_id = $lead->user_id;
        $quote->save();
        $quote->leads()->attach($lead->id);

        return $submission;
    }
}
