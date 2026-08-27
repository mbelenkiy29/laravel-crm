<?php

namespace Webkul\Funder\Services;

use Throwable;
use Webkul\Funder\Adapters\FunderAdapter;
use Webkul\Funder\Models\Submission;
use Webkul\Lead\Contracts\Lead as LeadContract;

class SubmitToFunders
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected FunderAdapter $adapter) {}

    /**
     * Submit a lead to each funder. One failure does not block the rest.
     *
     * @param  iterable<int, mixed>  $funders
     * @param  mixed  $docs
     * @return array<int, Submission>
     */
    public function submit(LeadContract $lead, iterable $funders, $docs = []): array
    {
        $submissions = [];

        foreach ($funders as $funder) {
            try {
                $submissions[] = $this->adapter->submit($lead, $funder, $docs);
            } catch (Throwable $exception) {
                $submission = new Submission;
                $submission->lead_id = $lead->id;
                $submission->funder_id = $funder->id;
                $submission->route = $funder->route;
                $submission->status = Submission::STATUS_ERRORED;
                $submission->error_message = $exception->getMessage();
                $submission->save();

                $submissions[] = $submission;
            }
        }

        return $submissions;
    }
}
