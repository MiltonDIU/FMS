<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\TeacherDeparture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * HR telling the FMS that a teacher has left.
 *
 * The one endpoint in /v1 that writes, and the only one a teacher's token
 * cannot reach: RequireTokenAbility admits only a token issued for it by
 * teachers:issue-hr-token.
 */
class HrDepartureController extends Controller
{
    public function store(Request $request, TeacherDeparture $departure): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', Rule::in(TeacherDeparture::reasons())],

            /*
             * Today or earlier. A resignation recorded in advance, with a
             * notice period still to serve, would otherwise take the profile
             * down the day it is entered rather than the day they go. HR sends
             * it once the day has come.
             */
            'leaving_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        /*
         * Every profile under the id, not the first. The duplicate report
         * finds the same person entered twice often enough, and choosing one
         * of them here would leave the other listed as if they had stayed.
         */
        $teachers = Teacher::where('employee_id', trim($data['employee_id']))->get();

        if ($teachers->isEmpty()) {
            return response()->json(['message' => 'No teacher has this employee id.'], 404);
        }

        if ($teachers->count() > 1) {
            return response()->json([
                'message' => 'More than one teacher profile has this employee id. Merge the duplicates in the panel, then send this again.',
            ], 409);
        }

        $result = $departure->record(
            $teachers->first(),
            $data['reason'],
            Carbon::createFromFormat('Y-m-d', $data['leaving_date'])->startOfDay(),
        );

        $teacher = $result['teacher']->load('employmentStatus');
        $period = $result['period'];

        return response()->json([
            'status' => $result['status'],
            'teacher' => [
                'id' => $teacher->id,
                'employee_id' => $teacher->employee_id,
                'name' => $teacher->full_name,
                'employment_status' => $teacher->employmentStatus?->name,
                'leaving_date' => $teacher->leaving_date?->toDateString(),
                'is_public' => (bool) $teacher->is_public,
            ],
            'service_period' => $period ? [
                'joined_on' => $period->joined_on?->toDateString(),
                'left_on' => $period->left_on?->toDateString(),
                'ended' => (bool) $period->ended,
                'end_reason' => $period->end_reason,
            ] : null,
        ]);
    }
}
