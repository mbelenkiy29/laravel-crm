<x-admin::layouts>
    <x-slot:title>
        @lang('funder::app.settings.funders.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.funders" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('funder::app.settings.funders.index.title')
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-4 text-lg font-semibold dark:text-gray-300">
                @if ($funder)
                    @lang('funder::app.settings.funders.index.edit-title')
                @else
                    @lang('funder::app.settings.funders.index.create-title')
                @endif
            </p>

            <form
                method="POST"
                action="{{ $funder ? route('admin.settings.funders.update', $funder->id) : route('admin.settings.funders.store') }}"
                class="flex max-w-xl flex-col gap-3"
            >
                @csrf

                @if ($funder)
                    @method('PUT')
                @endif

                <label class="flex flex-col gap-1 text-sm dark:text-gray-300">
                    <span>@lang('funder::app.settings.funders.index.name')</span>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $funder->name ?? '') }}"
                        required
                        class="rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
                    />
                    @error('name')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm dark:text-gray-300">
                    <span>@lang('funder::app.settings.funders.index.kind')</span>
                    <select
                        name="kind"
                        required
                        class="rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
                    >
                        @foreach (\Webkul\Funder\Models\Funder::KINDS as $kind)
                            <option
                                value="{{ $kind }}"
                                @selected(old('kind', $funder->kind ?? 'sandbox') === $kind)
                            >
                                {{ $kind }}
                            </option>
                        @endforeach
                    </select>
                    @error('kind')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm dark:text-gray-300">
                    <span>@lang('funder::app.settings.funders.index.route')</span>
                    <input
                        type="text"
                        name="route"
                        value="{{ old('route', $funder->route ?? '') }}"
                        class="rounded border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-950"
                    />
                </label>

                <label class="flex flex-col gap-1 text-sm dark:text-gray-300">
                    <span>@lang('funder::app.settings.funders.index.criteria')</span>
                    <textarea
                        name="criteria"
                        rows="6"
                        class="rounded border border-gray-300 px-3 py-2 font-mono text-xs dark:border-gray-700 dark:bg-gray-950"
                    >{{ old('criteria', isset($funder) && $funder->criteria !== null ? json_encode($funder->criteria, JSON_PRETTY_PRINT) : '') }}</textarea>
                    @error('criteria')
                        <span class="text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <button
                    type="submit"
                    class="primary-button w-fit"
                >
                    @if ($funder)
                        @lang('funder::app.settings.funders.index.update-btn')
                    @else
                        @lang('funder::app.settings.funders.index.save-btn')
                    @endif
                </button>
            </form>
        </div>

        <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            @if ($funders->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    @lang('funder::app.settings.funders.index.empty')
                </p>
            @else
                <table class="w-full text-left text-sm dark:text-gray-300">
                    <thead>
                        <tr>
                            <th class="border-b px-2 py-2">@lang('funder::app.settings.funders.index.datagrid.id')</th>
                            <th class="border-b px-2 py-2">@lang('funder::app.settings.funders.index.datagrid.name')</th>
                            <th class="border-b px-2 py-2">@lang('funder::app.settings.funders.index.datagrid.kind')</th>
                            <th class="border-b px-2 py-2">@lang('funder::app.settings.funders.index.datagrid.route')</th>
                            <th class="border-b px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($funders as $row)
                            <tr>
                                <td class="border-b px-2 py-2">{{ $row->id }}</td>
                                <td class="border-b px-2 py-2">{{ $row->name }}</td>
                                <td class="border-b px-2 py-2">{{ $row->kind }}</td>
                                <td class="border-b px-2 py-2">{{ $row->route }}</td>
                                <td class="border-b px-2 py-2">
                                    <a
                                        href="{{ route('admin.settings.funders.edit', $row->id) }}"
                                        class="mr-2 text-brandColor"
                                    >
                                        @lang('funder::app.acl.edit')
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.settings.funders.delete', $row->id) }}"
                                        class="inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="text-red-600"
                                        >
                                            @lang('funder::app.settings.funders.index.delete-btn')
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin::layouts>
