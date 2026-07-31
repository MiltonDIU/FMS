{{--
    Sharing and structured data for a teacher's profile.

    This used to build the whole payload inline. It now delegates to
    App\Helpers\SeoPayload, the same place the faculty, department and
    publication pages get theirs, so the four page types cannot drift apart and
    the escaping lives in one partial.

    The include path is unchanged on purpose: all four themes reference it from
    their profile page.

    Expects $teacher, $faculty and $department.
--}}
@include('frontend.partials.seo-tags', [
    'seo' => \App\Helpers\SeoPayload::forTeacher($teacher, $faculty, $department),
])
