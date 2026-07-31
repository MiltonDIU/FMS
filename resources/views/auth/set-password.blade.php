<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your password — {{ \App\Helpers\Branding::get('site_name') }}</title>
    {{-- Search engines have no business with a signed-in setup page. --}}
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/views/frontend/themes/theme_diu/assets/css/theme.css'])
    <style>{!! \App\Helpers\ColorPalette::cssRootBlock() !!}</style>
    {!! \App\Helpers\FontManager::googleLinks('theme_diu') !!}
    {!! \App\Helpers\FontManager::cssBlock('theme_diu') !!}
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-100 font-sans antialiased px-4 py-10">

    <main class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">

            <h1 class="font-display text-xl font-bold text-slate-900 mb-1">Choose your password</h1>
            <p class="text-sm text-slate-600 mb-6">
                Welcome, {{ $user->getFilamentName() }}. Your account was carried over from our
                previous records, so please set a password of your own to finish signing in.
            </p>

            @if ($errors->any())
                <div class="mb-5 rounded-lg border-l-4 border-red-400 bg-red-50 px-4 py-3">
                    <ul class="text-sm text-red-800 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('teacher.password.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        New password
                    </label>
                    <input id="password" name="password" type="password" required autofocus
                           autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-diu-primary focus:ring-2 focus:ring-diu-primary/30 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">
                        At least 8 characters, with letters and numbers.
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">
                        Confirm password
                    </label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                                  focus:border-diu-primary focus:ring-2 focus:ring-diu-primary/30 focus:outline-none">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-diu-primary px-4 py-2.5 text-sm font-semibold text-white
                               hover:bg-diu-primary-hover focus:outline-none focus:ring-2 focus:ring-diu-primary/40">
                    Set password and continue
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-slate-500">
            {{ \App\Helpers\Branding::get('site_name') }}
        </p>
    </main>

</body>
</html>
