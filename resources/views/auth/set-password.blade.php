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
                    <div class="relative">
                        <input id="password" name="password" type="password" required autofocus
                               autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 pl-3 pr-10 py-2 text-sm
                                      focus:border-diu-primary focus:ring-2 focus:ring-diu-primary/30 focus:outline-none">
                        <button type="button"
                                data-toggle-for="password"
                                title="Show password"
                                aria-label="Show password"
                                class="password-toggle absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <svg class="eye-icon h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                            </svg>
                            <svg class="eye-slash-icon h-5 w-5 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" />
                                <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" />
                            </svg>
                        </button>
                    </div>
                    {{--
                        The same rules TeacherPasswordSetupController::store()
                        enforces — change one, change the other. Each line ticks
                        as it is met, so the first attempt can be the right one
                        instead of learning the rules from an error. The breach
                        check cannot be done in the browser; it is stated so a
                        rejection for it is not a surprise.
                    --}}
                    <ul id="password-rules" class="mt-2 space-y-1 text-xs text-slate-500" aria-live="polite">
                        <li data-rule="length"><span class="mark">○</span> At least 8 characters</li>
                        <li data-rule="letter"><span class="mark">○</span> At least one letter (a–z)</li>
                        <li data-rule="number"><span class="mark">○</span> At least one number (0–9)</li>
                        <li data-rule="symbol"><span class="mark">○</span> At least one symbol, e.g. ! @ # $ % &amp; * ?</li>
                        <li data-rule="match"><span class="mark">○</span> Both passwords match</li>
                        <li><span class="mark">•</span> Must not be a password known from a public data breach (checked when you submit)</li>
                    </ul>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">
                        Confirm password
                    </label>
                    <div class="relative">
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 pl-3 pr-10 py-2 text-sm
                                      focus:border-diu-primary focus:ring-2 focus:ring-diu-primary/30 focus:outline-none">
                        <button type="button"
                                data-toggle-for="password_confirmation"
                                title="Show password"
                                aria-label="Show password"
                                class="password-toggle absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <svg class="eye-icon h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                            </svg>
                            <svg class="eye-slash-icon h-5 w-5 hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z" clip-rule="evenodd" />
                                <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z" />
                            </svg>
                        </button>
                    </div>
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

    <script>
        (function () {
            var password = document.getElementById('password');
            var confirmation = document.getElementById('password_confirmation');
            var rules = document.getElementById('password-rules');

            // Mirrors Laravel's Password rule: letters() is \p{L}, numbers()
            // is \p{N}, symbols() is any symbol, punctuation or space.
            var checks = {
                length: function (v) { return v.length >= 8; },
                letter: function (v) { return /\p{L}/u.test(v); },
                number: function (v) { return /\p{N}/u.test(v); },
                symbol: function (v) { return /[\p{Z}\p{S}\p{P}]/u.test(v); },
                match: function (v) { return v.length > 0 && v === confirmation.value; }
            };

            function update() {
                var value = password.value;

                Object.keys(checks).forEach(function (rule) {
                    var item = rules.querySelector('[data-rule="' + rule + '"]');
                    var met = checks[rule](value);

                    item.querySelector('.mark').textContent = met ? '✓' : '○';
                    // Inline colours: this page's stylesheet is the theme's,
                    // which is not built with classes from this file.
                    item.style.color = met ? '#047857' : (value.length ? '#b91c1c' : '');
                });
            }

            password.addEventListener('input', update);
            confirmation.addEventListener('input', update);

            // Toggle show/hide password visibility
            document.querySelectorAll('.password-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var inputId = btn.getAttribute('data-toggle-for');
                    var input = document.getElementById(inputId);
                    if (!input) return;

                    var isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');

                    var eyeIcon = btn.querySelector('.eye-icon');
                    var eyeSlashIcon = btn.querySelector('.eye-slash-icon');

                    if (isPassword) {
                        eyeIcon.classList.add('hidden');
                        eyeSlashIcon.classList.remove('hidden');
                        btn.setAttribute('title', 'Hide password');
                        btn.setAttribute('aria-label', 'Hide password');
                    } else {
                        eyeIcon.classList.remove('hidden');
                        eyeSlashIcon.classList.add('hidden');
                        btn.setAttribute('title', 'Show password');
                        btn.setAttribute('aria-label', 'Show password');
                    }
                });
            });
        })();
    </script>
</body>
</html>