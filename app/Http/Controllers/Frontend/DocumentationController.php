<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DocumentationController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect('/documents/user-guide/index.html');
    }

    public function show(string $slug): RedirectResponse
    {
        $map = [
            'teacher' => 'teacher.html',
            'teacher-guide' => 'teacher.html',
            'department-head' => 'department-head.html',
            'department-head-guide' => 'department-head.html',
            'admin' => 'admin.html',
            'admin-guide' => 'admin.html',
            'research' => 'research.html',
            'research-guide' => 'research.html',
        ];

        if (isset($map[$slug])) {
            return redirect('/documents/user-guide/' . $map[$slug]);
        }

        abort(404, 'User guide not found.');
    }
}
