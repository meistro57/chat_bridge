<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationTemplateRequest;
use App\Http\Requests\UpdateConversationTemplateRequest;
use App\Models\ConversationTemplate;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->orderByDesc('is_favorite')
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
        $files = $request->file('rag_files', []);

        $template = ConversationTemplate::create([
            ...$this->templateAttributes($data),
            'user_id' => $request->user()->id,
            'rag_files' => [],
        ]);
        $this->storeTemplateFiles($template, $files);

        return Redirect::route('templates.edit', $template)
            ->with('success', 'Template created successfully.');
    }

    public function storeFromChat(StoreConversationTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('rag_files', []);

        $template = ConversationTemplate::create([
            ...$this->templateAttributes($data),
            'user_id' => $request->user()->id,
            'rag_files' => [],
        ]);
        $this->storeTemplateFiles($template, $files);

        $target = url()->previous() ?: route('chat.create');

        return Redirect::to($target)->with('success', 'Template saved successfully.');
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

        $data = $request->validated();
        $filesToDelete = collect($data['rag_files_to_delete'] ?? [])->filter()->values()->all();

        $this->deleteTemplateFiles($template, $filesToDelete);
        $template->update($this->templateAttributes($data));
        $this->storeTemplateFiles($template, $request->file('rag_files', []));

        return Redirect::route('templates.edit', $template)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(Request $request, ConversationTemplate $template): RedirectResponse
    {
        $this->authorizeOwner($request, $template);
        $this->deleteTemplateFiles($template, $template->rag_files ?? []);

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
        $copy->rag_files = [];
        $copy->save();

        return Redirect::route('templates.edit', $copy)
            ->with('success', 'Template cloned.');
    }

    public function toggleFavorite(Request $request, ConversationTemplate $template): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $template);

        $template->update([
            'is_favorite' => ! $template->is_favorite,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'is_favorite' => (bool) $template->is_favorite,
            ]);
        }

        return back()->with('success', 'Template favorite status updated.');
    }

    public function clearFavorites(Request $request): JsonResponse|RedirectResponse
    {
        ConversationTemplate::query()
            ->where('user_id', $request->user()->id)
            ->where('is_favorite', true)
            ->update(['is_favorite' => false]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
            ]);
        }

        return redirect()->route('templates.index')->with('success', 'All template favorites cleared.');
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
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get(['id', 'name', 'is_favorite']);
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateAttributes(array $data): array
    {
        unset($data['rag_files'], $data['rag_files_to_delete']);

        return array_merge($data, [
            'rag_enabled' => (bool) ($data['rag_enabled'] ?? false),
            'rag_source_limit' => (int) ($data['rag_source_limit'] ?? 6),
            'rag_score_threshold' => (float) ($data['rag_score_threshold'] ?? 0.3),
        ]);
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     */
    private function storeTemplateFiles(ConversationTemplate $template, array|UploadedFile|null $files): void
    {
        $uploads = is_array($files) ? $files : ($files ? [$files] : []);
        if ($uploads === []) {
            return;
        }

        $paths = collect($template->rag_files ?? []);

        foreach ($uploads as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $safeFilename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = trim($safeFilename) !== '' ? $safeFilename : 'rag-document';
            $storedPath = $file->storeAs(
                "template-rag/{$template->user_id}/{$template->id}",
                $filename.'-'.Str::uuid().'.'.$file->getClientOriginalExtension()
            );

            $paths->push($storedPath);
        }

        $template->update(['rag_files' => $paths->unique()->values()->all()]);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteTemplateFiles(ConversationTemplate $template, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $current = collect($template->rag_files ?? []);
        $deleteSet = collect($paths)->filter(fn ($path) => is_string($path) && $path !== '');
        $basePrefix = "template-rag/{$template->user_id}/{$template->id}/";
        $safeDeleteSet = $deleteSet->filter(fn (string $path) => str_starts_with($path, $basePrefix))->values();

        foreach ($safeDeleteSet as $path) {
            Storage::disk('local')->delete($path);
        }

        $remaining = $current->reject(fn (string $path) => $safeDeleteSet->contains($path))->values()->all();
        $template->update(['rag_files' => $remaining]);
    }
}
