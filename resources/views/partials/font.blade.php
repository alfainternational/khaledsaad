{{-- تحميل الخط مبكرًا: يقصّر مدة ظهور الخط البديل (بند ٣٤) --}}
<link rel="preload" href="{{ asset('assets/fonts/Hacen-Tunisia.ttf') }}" as="font" type="font/ttf" crossorigin>
<style>
    @font-face {
        font-family: 'Hacen Tunisia';
        src: url('{{ asset('assets/fonts/Hacen-Tunisia.ttf') }}') format('truetype');
        font-style: normal;
        font-weight: 100 950;
        font-display: swap;
    }
</style>
