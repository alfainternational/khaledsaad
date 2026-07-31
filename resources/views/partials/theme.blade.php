{{-- يُزرع في <head> قبل CSS: يقرأ التفضيل ويثبّت data-theme قبل أول رسم فلا وميض --}}
<script>
    (function () {
        try {
            var saved = localStorage.getItem('theme');
            var theme = saved === 'dark' || saved === 'light'
                ? saved
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-theme-toggle]');
            if (!button) return;
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
        });
    })();
</script>
