<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'color' => 'nullable|string|max:20',
        ]);

        Tag::create([
            'account_id' => $request->user()->account_id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#10b981',
        ]);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        abort_if($tag->account_id !== $request->user()->account_id, 403);
        $tag->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }
}
