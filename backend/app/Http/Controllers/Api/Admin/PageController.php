<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends ApiController
{
    public function index(): JsonResponse
    {
        $pages = Page::orderBy('title')->get()->map(fn($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'status' => $p->status,
        ]);

        return $this->success($pages);
    }

    public function show(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);

        return $this->success($page->only([
            'id', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'status', 'updated_at',
        ]));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);

        $page->update($request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:published,draft'],
        ]));

        return $this->success(['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'content' => $page->content], 'Strona zaktualizowana.');
    }
}
