<script setup>
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps({ status: Number });
const msg = { 403: 'Anda tidak memiliki akses ke halaman ini.', 404: 'Halaman tidak ditemukan.', 419: 'Sesi kedaluwarsa. Muat ulang halaman.', 429: 'Terlalu banyak permintaan. Coba lagi sebentar.', 500: 'Terjadi kesalahan pada server.', 503: 'Aplikasi sedang dalam pemeliharaan.' };
</script>
<template>
  <Head :title="`Kesalahan ${status}`" />
  <div class="err-page"><div><div class="code">{{ status }}</div><p class="fg2" style="font-size:16px">{{ msg[status] || 'Terjadi kesalahan.' }}</p><Link href="/dashboard" class="btn c-blue">Kembali ke dashboard</Link></div></div>
</template>
