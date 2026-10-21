<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { aiModule, md } from '../lib/md';
import { toast } from '../lib/format';

// AI Assistant kontekstual: tombol mengambang di setiap halaman fitur, panel percakapan di kanan
const page = usePage();
const user = computed(() => page.props.auth?.user);
const ctx = computed(() => aiModule(page.url));
const visible = computed(() => !!ctx.value && user.value && user.value.role !== 'auditor');
const open = ref(false);
const info = ref(null);
const msgs = ref([]); // {role, content, refs?, model?}
const input = ref('');
const busy = ref(false);
const err = ref('');
const box = ref(null);
const key = computed(() => (ctx.value ? `${ctx.value.module}:${ctx.value.id || ''}` : ''));
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const call = async (url, body) => {
  const res = await fetch(url, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
  const data = await res.json().catch(() => ({}));
  if (res.status === 429) throw new Error('Terlalu banyak permintaan dalam satu menit. Tunggu sebentar lalu coba lagi.');
  if (!res.ok) throw new Error(data.message && !data.errors ? data.message : Object.values(data.errors || {}).flat()[0] || data.message || 'Permintaan gagal.');
  return data;
};
const load = async () => {
  if (!visible.value) return;
  err.value = '';
  try { info.value = await call(`/ai/assist?module=${encodeURIComponent(ctx.value.module)}`); } catch (e) { info.value = null; err.value = e.message; }
};
const scroll = () => nextTick(() => { if (box.value) box.value.scrollTop = box.value.scrollHeight; });
const ask = async (action, label) => {
  const q = action ? null : input.value.trim();
  if (busy.value || (!action && !q)) return;
  const history = msgs.value.filter((m) => !m.error).map((m) => ({ role: m.role, content: m.content.slice(0, 3800) })).slice(-6);
  msgs.value.push({ role: 'user', content: action ? label : q });
  input.value = '';
  busy.value = true; scroll();
  try {
    const d = await call('/ai/assist', { module: ctx.value.module, id: ctx.value.id, action: action || null, question: q, history });
    msgs.value.push({ role: 'assistant', content: d.text, refs: Array.isArray(d.refs) ? {} : d.refs || {}, model: d.model });
    if (info.value) info.value.remaining = d.remaining;
  } catch (e) {
    msgs.value.push({ role: 'assistant', content: e.message, error: true });
  } finally { busy.value = false; scroll(); }
};
const onKey = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(); } };
const copy = (t) => navigator.clipboard?.writeText(t).then(() => toast('Disalin.', 'ok'));
const reset = () => { msgs.value = []; };
const toggle = () => { open.value = !open.value; if (open.value && !info.value) load(); };
// percakapan mengikuti halaman: ganti halaman/objek → muat aksi baru, kosongkan riwayat
watch(key, () => { msgs.value = []; info.value = null; if (open.value) load(); });
const onEsc = (e) => { if (e.key === 'Escape' && open.value) open.value = false; };
const onOpen = () => { if (visible.value) { open.value = true; if (!info.value) load(); } };
onMounted(() => { window.addEventListener('keydown', onEsc); window.addEventListener('mr:ai-open', onOpen); });
onUnmounted(() => { window.removeEventListener('keydown', onEsc); window.removeEventListener('mr:ai-open', onOpen); });
</script>
<template>
  <template v-if="visible">
    <button v-show="!open" type="button" class="ai-fab" aria-label="Buka AI Assistant halaman ini" @click="toggle"><Icon name="spark" /><span>Asisten AI</span></button>
    <Teleport to="body">
      <div v-if="open" class="scrim clear ai-scrim" @click="open = false"></div>
      <aside v-if="open" class="drawer ai-drawer" role="dialog" aria-label="AI Assistant">
        <div class="drawer-h">
          <div><div class="kicker" style="margin:0">AI Assistant</div><h3 style="margin:2px 0 0;font-size:16px">{{ info?.label || 'Memuat…' }}</h3></div>
          <span class="row" style="gap:6px"><button v-if="msgs.length" type="button" class="btn sm ghost" @click="reset">Baru</button><button type="button" class="icon-btn c-indigo" aria-label="Tutup" @click="open = false"><Icon name="x" /></button></span>
        </div>
        <div ref="box" class="drawer-b ai-body">
          <div v-if="err" class="alert-box warn" style="margin:12px"><Icon name="alert" /><span>{{ err }}</span></div>
          <template v-else-if="info">
            <div v-if="!info.enabled" class="alert-box warn" style="margin:12px"><Icon name="alert" /><span>Fitur AI dinonaktifkan oleh administrator.</span></div>
            <div v-if="!msgs.length" class="ai-intro">
              <p class="muted" style="margin:0 0 10px">Analisis data halaman ini sesuai hak akses Anda. Pilih aksi cepat atau ketik pertanyaan.</p>
              <div class="ai-chips"><button v-for="a in info.actions" :key="a.key" type="button" class="ai-chip" :disabled="busy || !info.enabled" @click="ask(a.key, a.label)"><Icon name="spark" :size="14" />{{ a.label }}</button></div>
            </div>
            <div v-for="(m, i) in msgs" :key="i" class="ai-msg" :class="[m.role, { error: m.error }]">
              <div v-if="m.role === 'user'" class="ai-bubble">{{ m.content }}</div>
              <div v-else class="ai-answer"><div class="md" v-html="md(m.content, m.refs)"></div>
                <div v-if="!m.error" class="row ai-meta"><span class="hint">{{ m.model === 'local-heuristic' ? 'analisis lokal' : m.model }}</span><button type="button" class="btn sm ghost" @click="copy(m.content)"><Icon name="copy" :size="14" />Salin</button></div>
              </div>
            </div>
            <div v-if="busy" class="ai-msg assistant"><div class="ai-answer muted"><span class="ai-dots"><i></i><i></i><i></i></span> Menganalisis…</div></div>
            <div v-if="msgs.length && !busy" class="ai-chips" style="padding:0 14px 12px"><button v-for="a in info.actions" :key="a.key" type="button" class="ai-chip sm" :disabled="!info.enabled" @click="ask(a.key, a.label)">{{ a.label }}</button></div>
          </template>
          <div v-else class="empty">Memuat…</div>
        </div>
        <div class="ai-foot">
          <div class="ai-input"><textarea v-model="input" rows="2" maxlength="1500" placeholder="Tanya tentang data halaman ini…" aria-label="Pertanyaan untuk AI Assistant" :disabled="busy || !info?.enabled" @keydown="onKey"></textarea>
            <button type="button" class="icon-btn c-violet" aria-label="Kirim" :disabled="busy || !input.trim() || !info?.enabled" @click="ask()"><Icon name="send" /></button></div>
          <div class="row between hint" style="margin-top:6px;gap:8px;flex-wrap:wrap"><span>{{ info ? `${info.provider} · sisa kuota ${info.remaining}` : '' }}</span>
            <span class="row" style="gap:10px"><Link href="/ai/analytics" @click="open = false">AI Analitik</Link><Link href="/ai" @click="open = false">AI Risk Assistant</Link></span></div>
          <div class="hint" style="margin-top:4px">Saran AI bersifat pendukung; keputusan tetap pada pemilik risiko.</div>
        </div>
      </aside>
    </Teleport>
  </template>
</template>
