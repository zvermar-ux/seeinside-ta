<?php

namespace App\Http\Controllers\Admin;

use App\Models\LibraryFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** JSON for the "From library" picker in forms */
class LibraryController extends Controller
{
    public function browse(Request $request): JsonResponse
    {
        $u = $request->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->can('media.view') || $u->agency_id), 403);
        $agency = $request->integer('agency') ?: null;
        $q = LibraryFile::query()->visible($agency)
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('alt', 'like', "%{$s}%")->orWhere('path', 'like', "%{$s}%")))
            ->when($request->query('folder'), fn ($q, $f) => $q->where('folder', $f))
            ->latest('id');
        $page = max(1, $request->integer('page', 1));
        $per = 40;
        $total = (clone $q)->count();
        $items = $q->forPage($page, $per)->get()->map(fn (LibraryFile $f) => [
            'path' => $f->path, 'url' => $f->url(), 'name' => $f->name, 'w' => $f->width, 'h' => $f->height, 'size' => $f->sizeLabel(),
        ]);
        $folders = $page === 1 ? LibraryFile::query()->visible($agency)->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder') : [];

        return response()->json(['items' => $items, 'more' => $page * $per < $total, 'total' => $total, 'folders' => $folders]);
    }
}
