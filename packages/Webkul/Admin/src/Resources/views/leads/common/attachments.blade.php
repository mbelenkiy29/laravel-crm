@php
    $attachmentBuckets = \Webkul\Lead\Models\Lead::attachmentBuckets();
@endphp

<div
    class="flex flex-col gap-4"
    id="attachments"
>
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            @lang('admin::app.leads.create.attachments')
        </p>

        <p class="text-gray-600 dark:text-white">
            @lang('admin::app.leads.create.attachments-info')
        </p>
    </div>

    <div class="grid w-1/2 grid-cols-2 gap-4 max-md:w-full">
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.create.attachment-file')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="file"
                name="attachments[0][file]"
                accept=".pdf,application/pdf"
            />

            <x-admin::form.control-group.error control-name="attachments[0][file]" />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.create.attachment-bucket')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="select"
                name="attachments[0][bucket]"
                :value="'bank_statements'"
            >
                @foreach ($attachmentBuckets as $code => $label)
                    <option
                        value="{{ $code }}"
                        @selected($code === 'bank_statements')
                    >
                        @lang('admin::app.leads.attachment-buckets.'.$code)
                    </option>
                @endforeach
            </x-admin::form.control-group.control>
        </x-admin::form.control-group>
    </div>
</div>
