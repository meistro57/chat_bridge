<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationTemplateRequest;
use App\Http\Requests\UpdateConversationTemplateRequest;
use App\Models\ConversationTemplate;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ConversationTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->query('category');

        $templates = ConversationTemplate::query()
            ->where(function ($query) use ($user) {
                $query->where('is_public', true)
                    ->orWhere('user_id', $user->id);
            })
            ->byCategory($category)
            ->with(['personaA:id,name', 'personaB:id,name', 'user:id,name'])
            ->orderBy('name')
            ->get();

        $categories = ConversationTemplate::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => [
                'category' => $category,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Templates/Create', [
            'personas' => $this->loadPersonas(),
            'categories' => $this->loadCategories($request),
        ]);
    }

    public function store(StoreConversationTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $template = ConversationTemplate::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return Redirect::route('templates.edit', $template)
            ->with('success', 'Template created successfully.');
    }

    public function storeFromChat(StoreConversationTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ConversationTemplate::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return Redirect::route('chat.create')->with('success', 'Template saved successfully.');
    }

    public function edit(Request $request, ConversationTemplate $template): Response
    {
        $this->authorizeOwner($request, $template);

        return Inertia::render('Templates/Edit', [
            'template' => $template,
            'personas' => $this->loadPersonas(),
            'categories' => $this->loadCategories($request),
        ]);
    }

    public function update(UpdateConversationTemplateRequest $request, ConversationTemplate $template): RedirectResponse
    {
        $this->authorizeOwner($request, $template);

        $template->update($request->validated());

        return Redirect::route('templates.edit', $template)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(Request $request, ConversationTemplate $template): RedirectResponse
    {
        $this->authorizeOwner($request, $template);

        $template->delete();

        return Redirect::route('templates.index')
            ->with('success', 'Template deleted.');
    }

    public function use(Request $request, ConversationTemplate $template): RedirectResponse
    {
        $this->authorizeView($request, $template);

        return Redirect::route('chat.create', ['template' => $template->id]);
    }

    public function clone(Request $request, ConversationTemplate $template): RedirectResponse
    {
        $this->authorizeView($request, $template);

        $copy = $template->replicate();
        $copy->name = $template->name.' (Copy)';
        $copy->starter_message = $template->starter_message;
        $copy->is_public = false;
        $copy->user_id = $request->user()->id;
        $copy->save();

        return Redirect::route('templates.edit', $copy)
            ->with('success', 'Template cloned.');
    }

    private function authorizeOwner(Request $request, ConversationTemplate $template): void
    {
        if ($template->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function authorizeView(Request $request, ConversationTemplate $template): void
    {
        if ($template->is_public || $template->user_id === $request->user()->id || $template->user_id === null) {
            return;
        }

        abort(403);
    }

    private function loadPersonas(): array|\Illuminate\Database\Eloquent\Collection
    {
        return Persona::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function loadCategories(Request $request)
    {
        return ConversationTemplate::query()
            ->where(function ($query) use ($request) {
                $query->where('is_public', true)
                    ->orWhere('user_id', $request->user()->id);
            })
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}
