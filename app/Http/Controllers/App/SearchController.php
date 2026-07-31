<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * البحث الشامل في اللوحة (بند ٢٥ من خطة الواجهات).
 *
 * مستخدم عنده مشاريع وتقارير ومهام كان يتنقل بالقوائم فقط. البحث يقلّب
 * في أربعة أسطح دفعة واحدة، محصورًا دائمًا بما يملكه المستخدم.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $projectIds = Project::whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->pluck('id');

        $results = $term === '' ? null : [
            'projects' => Project::whereIn('id', $projectIds)
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('industry', 'like', $like))
                ->latest('id')->limit(8)->get(['id', 'slug', 'name', 'industry']),

            'reports' => Report::whereIn('project_id', $projectIds)
                ->where('status', 'published')
                ->where('title', 'like', $like)
                ->latest('id')->limit(8)->get(['id', 'title', 'score', 'created_at']),

            'tasks' => Task::whereIn('project_id', $projectIds)
                ->where('title', 'like', $like)
                ->latest('id')->limit(8)->with('project:id,slug,name')->get(),

            'tools' => Tool::where(fn ($query) => $query->where('title', 'like', $like)->orWhere('name', 'like', $like))
                ->orderBy('sort_order')->limit(8)->get(['id', 'key', 'title', 'status']),
        ];

        return view('app.search', ['term' => $term, 'results' => $results]);
    }
}
