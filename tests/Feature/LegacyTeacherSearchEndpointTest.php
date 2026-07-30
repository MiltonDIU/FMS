<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Guards the one API route that survived the Next.js frontend removal.
 *
 * It backs the legacy teacher search box on the admin "Create Teacher" page and
 * reads the `old_db` connection, so it must stay behind authentication. It also
 * lives in routes/web.php rather than routes/api.php, because the browser calls
 * it with the panel session cookie and the "api" middleware group is stateless.
 */
class LegacyTeacherSearchEndpointTest extends TestCase
{
    public function test_rejects_guest_ajax_request_with_401(): void
    {
        $response = $this->getJson('/api/teacher/search?q=750');

        $response->assertStatus(401);
    }

    public function test_redirects_guest_browser_request_to_panel_login(): void
    {
        $response = $this->get('/api/teacher/search?q=750');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_allows_authenticated_request_through_to_the_controller(): void
    {
        // Unsaved model: actingAs only needs an Authenticatable, and this keeps
        // the assertion about the auth boundary rather than the legacy database.
        $response = $this->actingAs(new User())->getJson('/api/teacher/search?q=750');

        $this->assertNotContains($response->status(), [301, 302, 401, 403]);
        $response->assertJsonStructure(['success']);
    }
}
