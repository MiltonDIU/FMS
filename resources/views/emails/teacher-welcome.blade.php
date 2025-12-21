<x-mail::message>
# Welcome to Faculty Management System

Dear **{{ $user->name }}**,

Your teacher account has been created successfully.

## Your Login Credentials

**Email:** {{ $user->email }}
**Password:** {{ $password }}

<x-mail::button :url="$loginUrl">
Login Now
</x-mail::button>

**Important:** Please change your password after your first login for security.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
