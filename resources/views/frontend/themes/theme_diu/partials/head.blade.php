<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'Faculty Directory - Daffodil International University' }}</title>

<!-- Fonts: Inter (sans), Space Grotesk (display), JetBrains Mono (mono) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Tailwind CSS (Play CDN) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    display: ['"Space Grotesk"', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                },
                colors: {
                    diu: {
                        'green-dark': '#0a2540',
                        'green': '#0e3d75',
                        'green-light': '#1d4ed8',
                        'green-hover': '#0b305c',
                        'blue-dark': '#071930',
                        'blue': '#0e3d75',
                        'blue-light': '#2563eb',
                        'orange': '#3b82f6',
                        'orange-light': '#60a5fa',
                        'orange-hover': '#1d4ed8',
                    }
                }
            }
        }
    }
</script>

<!-- Alpine.js for tabs / interactive bits -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
    body {
        background-color: #f4f8fc;
        background-image:
            radial-gradient(at 0% 0%, rgba(14, 61, 117, 0.06) 0px, transparent 50%),
            radial-gradient(at 100% 0%, rgba(37, 99, 235, 0.04) 0px, transparent 50%),
            radial-gradient(at 50% 100%, rgba(59, 130, 246, 0.02) 0px, transparent 50%),
            linear-gradient(to bottom right, #f5f9fc, #edf3f8);
        background-attachment: fixed;
        min-height: 100vh;
    }

    .glass-header {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.4);
    }

    .glass-panel {
        background: rgba(255, 255, 255, 0.45);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.6);
    }

    [x-cloak] { display: none !important; }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
