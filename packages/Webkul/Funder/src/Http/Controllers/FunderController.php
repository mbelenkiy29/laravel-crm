<?php

namespace Webkul\Funder\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Funder\DataGrids\FunderDataGrid;
use Webkul\Funder\Models\Funder;
use Webkul\Funder\Repositories\FunderRepository;

class FunderController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected FunderRepository $funderRepository) {}

    /**
     * Display a listing of the funders.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(FunderDataGrid::class)->process();
        }

        $funders = $this->funderRepository->all();

        return view('funder::settings.funders.index', [
            'funders' => $funders,
            'funder' => null,
        ]);
    }

    /**
     * Store a newly created funder.
     */
    public function store(): JsonResponse|RedirectResponse
    {
        $this->validate(request(), $this->rules());

        $funder = $this->funderRepository->create($this->payload());

        return $this->savedResponse($funder, trans('funder::app.settings.funders.index.create-success'));
    }

    /**
     * Show the form for editing the specified funder.
     */
    public function edit(int $id): View
    {
        $funder = $this->funderRepository->findOrFail($id);
        $funders = $this->funderRepository->all();

        return view('funder::settings.funders.index', compact('funders', 'funder'));
    }

    /**
     * Update the specified funder.
     */
    public function update(int $id): JsonResponse|RedirectResponse
    {
        $this->validate(request(), $this->rules());

        $this->funderRepository->findOrFail($id);

        $funder = $this->funderRepository->update($this->payload(), $id);

        return $this->savedResponse($funder, trans('funder::app.settings.funders.index.update-success'));
    }

    /**
     * Remove the specified funder.
     */
    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        $this->funderRepository->findOrFail($id);

        $this->funderRepository->delete($id);

        $message = trans('funder::app.settings.funders.index.delete-success');

        if (request()->expectsJson() || request()->ajax()) {
            return new JsonResponse(['message' => $message]);
        }

        session()->flash('success', $message);

        return redirect()->route('admin.settings.funders.index');
    }

    /**
     * Validation rules for create and update.
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string',
            'kind' => 'required|in:'.implode(',', Funder::KINDS),
            'route' => 'nullable|string',
            'criteria' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if ($value === null || $value === '' || is_array($value)) {
                    return;
                }

                if (! is_string($value)) {
                    $fail(trans('funder::app.settings.funders.index.invalid-criteria'));

                    return;
                }

                json_decode($value, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail(trans('funder::app.settings.funders.index.invalid-criteria'));
                }
            }],
        ];
    }

    /**
     * Mass-assignable payload. `portal_task` is ignored.
     */
    protected function payload(): array
    {
        $data = request()->only(['name', 'kind', 'route', 'criteria']);

        $data['criteria'] = $this->normalizeCriteria($data['criteria'] ?? null);

        return $data;
    }

    /**
     * Accept null, empty object, JSON string, or array.
     */
    protected function normalizeCriteria(mixed $criteria): ?array
    {
        if ($criteria === null || $criteria === '') {
            return null;
        }

        if (is_string($criteria)) {
            $decoded = json_decode($criteria, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (is_array($criteria)) {
            return $criteria;
        }

        return null;
    }

    /**
     * JSON for XHR, redirect for HTML forms.
     */
    protected function savedResponse($funder, string $message): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson() || request()->ajax()) {
            return new JsonResponse([
                'data' => $funder,
                'message' => $message,
            ]);
        }

        session()->flash('success', $message);

        return redirect()->route('admin.settings.funders.index');
    }
}
