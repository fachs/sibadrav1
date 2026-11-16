@vite(['resources/js/app.js','resources/js/layout.js'])
<script src="{{ asset('js/format-rupiah.js') }}"></script>
<script src="{{ asset('js/tahun-anggaran.js') }}"></script>
@yield('script')
@yield('script-bottom')
