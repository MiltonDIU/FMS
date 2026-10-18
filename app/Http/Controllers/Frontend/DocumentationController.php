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
            'head' => 'department-head.html',
            'associate-head' => 'department-head.html',
            'department-head' => 'department-head.html',
            'department-head-guide' => 'department-head.html',
            'dean' => 'dean.html',
            'associate-dean' => 'dean.html',
            'dean-guide' => 'dean.html',
            'registrar' => 'registrar.html',
            'registrar-guide' => 'registrar.html',
            'admin' => 'admin.html',
            'admin-guide' => 'admin.html',
            'super-admin' => 'super-admin.html',
            'super-admin-guide' => 'super-admin.html',
            'research' => 'research.html',
            'research-team' => 'research.html',
            'research-guide' => 'research.html',
        ];

        if (isset($map[$slug])) {
            return redirect('/documents/user-guide/' . $map[$slug]);
        }

        abort(404, 'User guide not found.');
    }
}
