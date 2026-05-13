<?php

namespace App\Http\Controllers;

use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Http\Requests\StoreEntityRequest;
use App\Http\Requests\UpdateEntityRequest;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function index(Request $request): Response
    {
        $entities = $request->user()
            ->entities()
            ->orderByRaw("CASE WHEN type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color']);

        return Inertia::render('entities/Index', [
            'entities' => $entities,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Entity::class);

        return Inertia::render('entities/Create', [
            'colors' => $this->colorOptions(),
        ]);
    }

    public function store(StoreEntityRequest $request): RedirectResponse
    {
        Gate::authorize('create', Entity::class);

        $request->user()->entities()->create([
            'name' => $request->string('name'),
            'type' => EntityType::Llc,
            'color' => EntityColor::from($request->string('color')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity created.')]);

        return to_route('entities.index');
    }

    public function edit(Entity $entity): Response
    {
        Gate::authorize('update', $entity);

        return Inertia::render('entities/Edit', [
            'entity' => $entity->only(['id', 'name', 'type', 'color']),
            'colors' => $this->colorOptions(),
        ]);
    }

    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('update', $entity);

        $entity->update([
            'name' => $request->string('name'),
            'color' => EntityColor::from($request->string('color')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity updated.')]);

        return to_route('entities.index');
    }

    public function destroy(Entity $entity): RedirectResponse
    {
        Gate::authorize('delete', $entity);

        $entity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entity deleted.')]);

        return to_route('entities.index');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function colorOptions(): array
    {
        return array_map(
            fn (EntityColor $color) => ['value' => $color->value, 'label' => $color->label()],
            EntityColor::cases(),
        );
    }
}
