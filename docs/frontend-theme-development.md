# Building a New Frontend Theme

How to add a theme to the public faculty directory (the Blade + Livewire
frontend, not the Filament admin panel).

Every path below is relative to `resources/views/frontend/themes/`, and
`theme_{slug}` means your new folder — e.g. `theme_aurora`.

Reference implementation: **`theme_diu`**. It is the most complete theme and the
fallback for optional views, so copy from it rather than starting empty.

---

## 1. Quick start

```bash
# 1. Copy a complete theme
cp -r resources/views/frontend/themes/theme_diu \
      resources/views/frontend/themes/theme_aurora

# 2. Replace every hardcoded slug inside it  (see §5 — this is the #1 mistake)
grep -rl 'theme_diu' resources/views/frontend/themes/theme_aurora \
  | xargs sed -i 's/theme_diu/theme_aurora/g'

# 3. Build — Vite discovers the theme's CSS/JS automatically
npm run build

# 4. Seed the theme's typography settings
php artisan db:seed --class=ThemeSettingsSeeder

# 5. Clear caches, then pick it in the admin panel
php artisan optimize:clear
#    Admin → System Settings → Theme and Color Customization → Active Theme
```

If the theme does not appear in the Active Theme dropdown, see §6.

---

## 2. Required files

A theme is only usable when all of these exist. Anything missing here is a
runtime error, not a graceful fallback.

### 2.1 The layout — this file decides whether the theme exists at all

```
theme_{slug}/layouts/app.blade.php
```

Theme discovery keys off **this exact path**. Both
`SystemSettings::getAvailableThemes()` and `ThemeSettingsSeeder::discoverThemes()`
skip any folder without it. That is deliberate: a theme with no layout would
crash the frontend the moment an admin selected it, so it is hidden instead of
offered. A folder missing this file is silently ignored — no warning.

### 2.2 Page views

Each controller renders `frontend.themes.{active_theme}.{view}`, so all five must
exist under `theme_{slug}/`:

| View                    | Rendered by                        | URL |
| ----------------------- | ---------------------------------- | --- |
| `home.blade.php`        | `HomeController@index`             | `/` and `/{faculty}` |
| `department.blade.php`  | `DepartmentController@show`        | `/{faculty}/{department}` |
| `contact.blade.php`     | `DepartmentController@contact`     | `/{faculty}/{department}/contact` |
| `profile.blade.php`     | `TeacherController@show`           | `/{faculty}/{department}/{teacher}` |
| `publication.blade.php` | `PublicationController@show`       | `/{faculty}/{department}/{teacher}/publication/{slug}` |

The `vcard` and `cv` routes are **not** themed — the CV renders from the shared
`resources/views/frontend/cv.blade.php`. See §8.

### 2.3 Assets

```
theme_{slug}/assets/css/theme.css
theme_{slug}/assets/js/theme.js
```

Vite finds these by scanning the themes directory, so `vite.config.js` needs no
edit. The scan runs when the config loads, which means **adding a theme still
requires `npm run build`**.

---

## 3. Optional files

### Livewire component views

```
theme_{slug}/livewire/teacher-search.blade.php
theme_{slug}/livewire/department-search.blade.php
```

`TeacherSearch` and `DepartmentSearch` look for the active theme's copy and fall
back to `theme_diu`'s if it is absent. Omit them and search still works, styled
like `theme_diu`.

### Partials

`partials/` is yours to organise — nothing outside the theme includes these, only
your own views do. `theme_diu` splits them as:

```
partials/head.blade.php          partials/footer.blade.php
partials/header.blade.php        partials/sidebar.blade.php
partials/pagination.blade.php    partials/teacher_card.blade.php
partials/social_icon.blade.php   partials/profile/*.blade.php   (9 profile tabs)
```

One partial path is special. Anything matching
`frontend.themes.*.partials.header` receives three variables automatically from a
view composer in `AppServiceProvider`:

```
$facultiesCount  $departmentsCount  $teachersCount
```

Keep your header at `partials/header.blade.php` to inherit them. Move it and the
counts become undefined — query them yourself or add another composer.

---

## 4. Layout integration points

The layout is where the theme meets the settings system. Copy this skeleton and
substitute your slug. Order matters where noted.

```blade
<!DOCTYPE html>
<html lang="en" class="{{ \App\Helpers\Appearance::htmlClass() }}">
<head>
    {{-- Stamps `dark` on <html> before first paint. Without it the page flashes
         light before JS corrects it. --}}
    <script>{!! \App\Helpers\Appearance::preloadScript() !!}</script>

    @include('frontend.themes.theme_{slug}.partials.head')

    @vite([
        'resources/views/frontend/themes/theme_{slug}/assets/css/theme.css',
        'resources/views/frontend/themes/theme_{slug}/assets/js/theme.js',
    ])

    {{-- Google Fonts request built from the per-theme font pickers --}}
    {!! \App\Helpers\FontManager::googleLinks('theme_{slug}') !!}
    {{-- Hand-entered stylesheet URLs from the custom font library --}}
    {!! \App\Helpers\FontManager::customStylesheetLinks('theme_{slug}') !!}

    <style>
        {!! \App\Helpers\ColorPalette::cssRootBlock() !!}
    </style>

    {{-- MUST come after @vite. It re-declares --font-* on :root, and the later
         declaration is the one that wins over the build-time @theme tokens. --}}
    {!! \App\Helpers\FontManager::cssBlock('theme_{slug}') !!}
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

    @include('frontend.themes.theme_{slug}.partials.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('frontend.themes.theme_{slug}.partials.footer')

    @livewireScripts
</body>
</html>
```

Drop any of the helper calls and you lose the corresponding admin control:

| Omitted                            | Consequence |
| ---------------------------------- | ----------- |
| `Appearance::htmlClass()`           | Dark mode never applies on first render |
| `Appearance::preloadScript()`       | Flash of light theme before JS runs |
| `FontManager::googleLinks()`        | No fonts load; falls back to system sans |
| `FontManager::customStylesheetLinks()` | Externally hosted custom fonts never load |
| `FontManager::cssBlock()`           | Font pickers, base size and weights do nothing |
| `ColorPalette::cssRootBlock()`      | Palette and custom brand color do nothing |
| `@livewireScripts`                  | Search and dark-mode toggle both dead |

---

## 5. Slug strings you must replace

Copying a theme leaves the old slug in places `grep` will find but the eye will
not. **This is the most common reason a new theme renders as a broken copy of the
old one.** In the layout alone:

1. `@include('frontend.themes.theme_diu.partials.head')` (and header, footer)
2. both `@vite([...])` asset paths
3. all three `FontManager::*('theme_diu')` arguments

Then check every page view for `@extends('frontend.themes.theme_diu.layouts.app')`
and any `@include` inside your partials.

Verify nothing was missed:

```bash
grep -rn 'theme_diu' resources/views/frontend/themes/theme_aurora
```

An empty result means you are clean. A stale slug here means your theme loads
another theme's CSS and font settings while appearing to work.

---

## 6. `theme.css` structure

```css
@import 'tailwindcss';

/* Classes assembled inside PHP expressions — e.g. {{ $active ? 'bg-x' : 'bg-y' }}
   Tailwind's scanner cannot see them, so they must be listed or they are never
   generated and the element renders unstyled. */
@source inline("bg-slate-100 text-slate-900 border-transparent ...");

/* Dark mode from the `.dark` class on <html>, not prefers-color-scheme.
   Appearance + theme.js toggle that class. Omit this and dark mode is dead. */
@custom-variant dark (&:where(.dark, .dark *));

/* Blade/JS sources Tailwind should scan, including framework pagination views. */
@source '../../../../../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../../../../../storage/framework/views/*.php';
@source '../../../../../../**/*.blade.php';
@source '../../../../../../**/*.js';

@theme {
    /* Build-time font defaults. FontManager::cssBlock() re-declares these at
       runtime, so these apply only until the settings resolve. Keep them in
       sync with FontManager::DEFAULTS so both paths look the same.
       'Mina' trails the Latin families so Bangla falls back per-character. */
    --font-sans: 'Manrope', 'Mina', ui-sans-serif, system-ui, sans-serif;
    --font-display: 'Montserrat', 'Mina', ui-sans-serif, system-ui, sans-serif;
    --font-bangla: 'Mina', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;

    /* All ten must be declared, or the matching utilities are never generated
       and ColorPalette's runtime overrides have nothing to override. */
    --color-diu-primary-dark: #002652;
    --color-diu-primary: #034ea2;
    --color-diu-primary-light: #0072bc;
    --color-diu-primary-hover: #023b7a;
    --color-diu-secondary-dark: #002652;
    --color-diu-secondary: #034ea2;
    --color-diu-secondary-light: #0072bc;
    --color-diu-accent: #0072bc;
    --color-diu-accent-light: #4da3d9;
    --color-diu-accent-hover: #005a96;
}
```

The relative `../` depth in `@source` is counted from
`resources/views/frontend/themes/theme_{slug}/assets/css/` to the project root —
six levels. Keep the same nesting and you can copy the lines verbatim.

---

## 7. How fonts and colors resolve

### Fonts — three layers, first match wins

```
1. a real value in the `settings` table     ← the per-theme picker
2. FontManager::DEFAULTS / SIZE_DEFAULT / WEIGHT_DEFAULTS
3. the @theme tokens in your theme.css      ← build-time
```

Layer 2 exists because these rows are seeded and can hold `NULL`;
`Setting::get()` returns a stored `NULL` verbatim rather than falling back to its
default, so `FontManager` treats blank as unset.

Four roles per theme, each stored as `theme_{slug}_font_{role}`:

| Role      | Utility         | Default          |
| --------- | --------------- | ---------------- |
| `sans`    | `font-sans`     | Manrope          |
| `display` | `font-display`  | Montserrat       |
| `mono`    | `font-mono`     | JetBrains Mono   |
| `bangla`  | `font-bangla`   | Mina             |

The Bangla family is appended behind `sans` and `display` automatically. Browsers
pick fonts per character, so mixed English/Bangla text resolves without tagging
each Bangla string — `font-bangla` is there for when you want it explicitly.

### Adding a Google font

Admin → System Settings → **Global Custom Font Library** → Add Font →
source *Google Fonts (by name)*, then enter the family exactly as Google spells
it (e.g. `Noto Serif Bengali`) and optionally a weight list. It then appears in
every per-theme font dropdown. No URL, no code change.

### Two css2 rules worth knowing

`FontManager` builds the request, but if you touch `PRESETS`:

- The **`wght@` axis prefix is mandatory**. `family=Inter:400;700` returns
  **400 Bad Request**, and one bad family fails the whole request — every font on
  the page silently disappears.
- A **static list** may name weights a family does not publish; css2 tolerates it.
  A **variable range** like `100..900` on a family with no variable axis returns
  **400**. Verify against the live API before committing a range.

### Weights the themes actually use

The stock themes use `font-medium` (500) through `font-black` (900). A preset that
stops at 700 makes the browser synthesise the heavier cuts, which looks visibly
worse than the real ones. Prefer a variable range reaching 900 where the family
offers it.

### Colors

`ColorPalette::cssRootBlock()` emits `:root` overrides for the same ten
`--color-diu-*` keys, derived from the palette or the admin's custom brand color.
Your `@theme` values are the build-time defaults that make the utilities exist.

---

## 8. PDF / CV exports

The CV is **not** themed. It renders from `resources/views/frontend/cv.blade.php`
through dompdf.

`FontManager::pdfCssBlock()` applies the active theme's font to the PDF, but only
when it can be embedded, because dompdf is strict:

- its stylesheet parser accepts a URL source **only** as `format('truetype')`
- `php-font-lib` ships **no WOFF2 reader**
- Google Fonts serves **only WOFF2** to modern clients

So a Google font can never reach the PDF. To put a theme font in the CV, upload
the family as a **`.ttf`** (or `.otf`) via the custom font library and assign it to
the `sans` or `display` role. Otherwise `pdfCssBlock()` returns an empty string and
the template's own stack stands.

`storage/fonts/` must exist and be writable — dompdf writes font metrics (`.ufm`)
there. It is tracked via `storage/fonts/.gitignore`; if it is missing, any custom
font in a PDF fails with `fopen(...ufm): No such file or directory`.

dompdf also has no complex-script shaping, so Bangla renders incorrectly in PDFs
regardless of font. Fixing that means replacing dompdf with a headless-Chrome
renderer.

---

## 9. Registering settings

```bash
php artisan db:seed --class=ThemeSettingsSeeder
```

Seeds nine settings for every discovered theme, plus `active_theme` when it is
not yet set. Safe to re-run:

- **missing** row → inserted with the default
- row holding **NULL or blank** → repaired to the default
- row holding a **real value** → left alone, only its metadata refreshed

So an admin's font choices survive a re-seed, and a theme added later is picked up
without touching the existing ones. `ThemeSettingsSeeder` also runs as part of
`php artisan db:seed`.

---

## 10. Troubleshooting

| Symptom | Cause |
| ------- | ----- |
| Theme missing from the Active Theme dropdown | No `layouts/app.blade.php`. Discovery skips the folder silently |
| Theme renders unstyled | `npm run build` not run after adding the folder — Vite's scan happens at config load |
| New theme looks identical to the one you copied | A stale `theme_diu` string remains. See §5 |
| No fonts at all, everything system sans | Layout is missing `FontManager::googleLinks()`, or a `PRESETS` entry lost its `wght@` prefix |
| Font pickers have no effect | `FontManager::cssBlock()` missing, or placed **before** `@vite` |
| Colors ignore the palette | `ColorPalette::cssRootBlock()` missing, or `@theme` does not declare all ten `--color-diu-*` |
| Dark mode does nothing | `@custom-variant dark` missing from `theme.css`, or `Appearance::htmlClass()` missing from `<html>` |
| Dark mode flashes light on load | `Appearance::preloadScript()` missing from `<head>` |
| Dark mode lost after navigating | `theme.js` not loaded — it re-stamps `.dark` after Livewire's `wire:navigate` DOM swap |
| Search box unstyled or dead | `@livewireScripts` missing, or a broken `livewire/*.blade.php` override. Delete yours to fall back to `theme_diu` |
| Some classes never generated | Built inside a PHP expression. Add them to `@source inline(...)` |
| Header counts undefined | Header partial moved off `partials/header.blade.php`, losing the view composer |
| Theme font absent from CV PDF | Expected unless uploaded as `.ttf`. See §8 |

---

## 11. Checklist

```
[ ] theme_{slug}/layouts/app.blade.php exists
[ ] home, department, contact, profile, publication views exist
[ ] assets/css/theme.css and assets/js/theme.js exist
[ ] theme.css has @import 'tailwindcss', @custom-variant dark, @source lines
[ ] @theme declares 4 --font-* and all 10 --color-diu-* tokens
[ ] layout has Appearance::htmlClass + preloadScript
[ ] layout has both FontManager link helpers and cssBlock AFTER @vite
[ ] layout has ColorPalette::cssRootBlock
[ ] layout has @yield('content') and @livewireScripts
[ ] grep -rn 'theme_diu' <your theme>   returns nothing
[ ] npm run build
[ ] php artisan db:seed --class=ThemeSettingsSeeder
[ ] php artisan optimize:clear
[ ] selected in System Settings, and all five URLs load
[ ] dark mode toggles and survives navigation
[ ] changing a font in System Settings visibly changes the theme
```
