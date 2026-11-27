import { computed, reactive, ref, unref, watch } from 'vue';

/* Paginasi sisi klien untuk tabel turunan (tab detail, pratinjau impor) yang datanya sudah dimuat bersama halaman. */
export function usePager(source, per = 10) {
  const page = ref(1);
  const rows = computed(() => (typeof source === 'function' ? source() : unref(source)) || []);
  const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / per)));
  watch(pages, (n) => { if (page.value > n) page.value = n; });
  return reactive({
    page,
    pages,
    per,
    total: computed(() => rows.value.length),
    from: computed(() => (rows.value.length ? (page.value - 1) * per + 1 : 0)),
    to: computed(() => Math.min(page.value * per, rows.value.length)),
    items: computed(() => rows.value.slice((page.value - 1) * per, page.value * per)),
    go: (n) => { page.value = Math.min(Math.max(1, n), pages.value); },
  });
}
