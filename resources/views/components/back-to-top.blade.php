{{--
    Back to top button.
    Floats at the bottom right on long pages and smoothly returns the reader to the top.
    Shared across all themes.
--}}
<button
    type="button"
    id="back-to-top"
    aria-label="Back to top"
    title="Back to top"
    class="back-to-top-btn"
>
    <svg class="back-to-top-icon" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
</button>

<style>
.back-to-top-btn {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 50;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background-color: var(--color-diu-primary, #034ea2);
    color: var(--diu-on-primary, #ffffff);
    box-shadow: 0 10px 25px -5px rgba(0, 38, 82, 0.35), 0 4px 6px -2px rgba(0, 0, 0, 0.1);
    opacity: 0;
    pointer-events: none;
    transform: translateY(16px);
    transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                filter 0.2s ease,
                background-color 0.2s ease;
    cursor: pointer;
    outline: none;
    -webkit-tap-highlight-color: transparent;
}

.back-to-top-btn:focus-visible {
    box-shadow: 0 0 0 3px rgba(3, 78, 162, 0.3), 0 10px 25px -5px rgba(0, 38, 82, 0.35);
}

.back-to-top-btn.is-visible {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.back-to-top-btn:hover {
    filter: brightness(1.12);
    transform: translateY(-2px);
    box-shadow: 0 14px 28px -5px rgba(0, 38, 82, 0.45), 0 6px 10px -2px rgba(0, 0, 0, 0.15);
}

.back-to-top-btn.is-visible:hover {
    transform: translateY(-2px);
}

.back-to-top-btn:active,
.back-to-top-btn.is-visible:active {
    transform: translateY(0) scale(0.94);
}

.back-to-top-icon {
    width: 1.25rem;
    height: 1.25rem;
    transition: transform 0.2s ease;
}

.back-to-top-btn:hover .back-to-top-icon {
    transform: translateY(-2px);
}

@media (min-width: 640px) {
    .back-to-top-btn {
        bottom: 2rem;
        right: 2rem;
        width: 3rem;
        height: 3rem;
    }
    .back-to-top-icon {
        width: 1.35rem;
        height: 1.35rem;
    }
}
</style>

<script>
(function () {
    function initBackToTop() {
        var btn = document.getElementById('back-to-top');
        if (!btn) return;

        var ticking = false;

        function checkScroll() {
            var scrollY = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
            var docHeight = Math.max(
                document.body.scrollHeight, document.documentElement.scrollHeight,
                document.body.offsetHeight, document.documentElement.offsetHeight,
                document.body.clientHeight, document.documentElement.clientHeight
            );
            var isLongPage = (docHeight - window.innerHeight) > 300;

            if (isLongPage && scrollY > 300) {
                btn.classList.add('is-visible');
            } else {
                btn.classList.remove('is-visible');
            }
            ticking = false;
        }

        if (window.__fmsBackToTopScrollHandler) {
            window.removeEventListener('scroll', window.__fmsBackToTopScrollHandler);
            window.removeEventListener('resize', window.__fmsBackToTopScrollHandler);
        }

        window.__fmsBackToTopScrollHandler = function () {
            if (!ticking) {
                window.requestAnimationFrame(checkScroll);
                ticking = true;
            }
        };

        window.addEventListener('scroll', window.__fmsBackToTopScrollHandler, { passive: true });
        window.addEventListener('resize', window.__fmsBackToTopScrollHandler, { passive: true });

        btn.onclick = function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        };

        checkScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBackToTop);
    } else {
        initBackToTop();
    }

    document.addEventListener('livewire:navigated', initBackToTop);
})();
</script>
