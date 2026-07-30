<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Intentionally empty.
|
| The public read API that fed the planned Next.js frontend has been removed —
| the frontend is a Blade + Livewire monolith served from routes/web.php.
|
| The one endpoint still in use (the legacy teacher search box on the admin
| "Create Teacher" page) lives in routes/web.php instead, because it is called
| from the browser with the panel's session cookie and the "api" middleware
| group is stateless.
|
| The ERP import preview (FrontendApiController::previewTeacherImport) is
| intentionally route-less — CreateTeacher and SystemSettings call it
| in-process, not over HTTP.
|--------------------------------------------------------------------------
*/
