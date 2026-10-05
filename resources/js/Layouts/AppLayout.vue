<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { fmt, toast } from '../lib/format';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const badges = computed(() => page.props.badges || {});
const navOpen = ref(false);
const menuOpen = ref(false);
const q = ref('');

const NAV = [
  { g: 'Dashboard', items: [['dashboard.executive', '/dashboard/executive', 'Executive Dashboard', 'grid', ['super_admin', 'risk_admin', 'risk_manager', 'management', 'auditor']], ['dashboard', '/dashboard', 'Risk Dashboard', 'pulse'], ['kris.index', '/kris', 'KRI & Early Warning', 'gauge'], ['alerts.index', '/alerts', 'Peringatan', 'bell']] },
  { g: 'Manajemen Risiko', items: [['context.index', '/context', 'Konteks & Konsultasi', 'compass'], ['criteria.index', '/criteria', 'Kriteria & Taksonomi', 'layers'], ['risks.index', '/risks', 'Risk Register', 'list'], ['risks.matrix', '/risks/matrix', 'Peta Risiko (Matriks)', 'target'], ['risks.evaluation', '/risks/evaluation', 'Evaluasi Risiko', 'scale'], ['risks.residual', '/risks/residual', 'Monitoring Residual', 'repeat'], ['reviews.index', '/reviews', 'Risk Review', 'refresh']] },
  { g: 'Penanganan & Kontrol', items: [['action-plans.index', '/action-plans', 'Mitigasi & Action Plan', 'tasks'], ['controls.index', '/controls', 'Kontrol & Efektivitas', 'shield'], ['improvements.index', '/improvements', 'Perbaikan Berkelanjutan', 'up']] },
  { g: 'Pemantauan', items: [['incidents.index', '/incidents', 'Insiden', 'alert'], ['losses.index', '/incidents/losses', 'Loss Event Database', 'bolt']] },
  { g: 'Tata Kelola', items: [['objectives.index', '/organization/objectives', 'Pemetaan Sasaran', 'flag'], ['units.index', '/organization/units', 'Struktur Organisasi', 'users'], ['framework.index', '/framework', 'Kerangka ISO 31000', 'book'], ['approvals.index', '/approvals', 'Persetujuan', 'inbox']] },
  { g: 'Pelaporan & Dokumen', items: [['reports.index', '/reports', 'Laporan', 'file'], ['documents.index', '/documents', 'Dokumen & Bukti', 'folder'], ['ai.index', '/ai', 'AI Risk Assistant', 'spark']] },
  { g: 'Administrasi', items: [['users.index', '/admin/users', 'Pengguna & Akun', 'key', ['super_admin']], ['audit.index', '/admin/audit', 'Audit Trail', 'clock', ['super_admin', 'risk_admin', 'risk_manager', 'auditor']], ['settings.organization', '/settings/organization', 'Pengaturan Organisasi', 'lock', ['super_admin', 'risk_admin']], ['settings.mail', '/admin/mail', 'Email & SMTP', 'send', ['super_admin']]] },
];
const allowed = (it) => !it[4] || it[4].includes(user.value?.role);
const current = computed(() => page.url.split('?')[0]);
const isOn = (href) => current.value === href || (href !== '/dashboard' && href !== '/risks' && current.value.startsWith(href + '/')) || (href === '/risks' && /^\/risks\/(\d+|create)/.test(current.value));
const badgeFor = (name) => (name === 'approvals.index' ? badges.value.approvals : name === 'alerts.index' ? badges.value.alerts : 0);

const toggleTheme = () => { const r = document.documentElement; const cur = r.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); r.dataset.theme = cur === 'dark' ? 'light' : 'dark'; try { localStorage.setItem('mr-theme', r.dataset.theme); } catch (e) { /* */ } };
const logout = () => router.post('/logout');
const search = () => { if (q.value.trim()) router.get('/risks', { q: q.value.trim() }); };
watch(() => page.props.flash, (f) => { if (f?.success) toast(f.success, 'ok'); if (f?.error) toast(f.error, 'bad'); if (f?.warning) toast(f.warning, 'warn'); }, { deep: true, immediate: true });
router.on('navigate', () => { navOpen.value = false; menuOpen.value = false; });
</script>
<template>
  <div class="app">
    <aside class="side" :class="{ open: navOpen }" aria-label="Navigasi utama">
      <div class="brand"><span class="brand-mark" aria-hidden="true"><i v-for="(k, i) in ['l', 'm', 'h', 'm', 'h', 'vh', 'h', 'vh', 'vh']" :key="i" :style="`background:var(--lv-${k})`"></i></span><span><b>ManRisk</b><small>ERM · ISO 31000:2018</small></span></div>
      <nav class="nav">
        <div v-for="g in NAV" :key="g.g" class="nav-g" v-show="g.items.some(allowed)">
          <span>{{ g.g }}</span>
          <template v-for="it in g.items" :key="it[0]">
            <Link v-if="allowed(it)" :href="it[1]" :class="{ on: isOn(it[1]) }" :aria-current="isOn(it[1]) ? 'page' : null"><Icon :name="it[3]" />{{ it[2] }}<span v-if="badgeFor(it[0])" class="cnt">{{ badgeFor(it[0]) }}</span></Link>
          </template>
        </div>
      </nav>
      <div class="side-foot">{{ user?.organization?.name }}<br><span class="mono">{{ user?.organization?.code }}</span></div>
    </aside>
    <div class="main">
      <header class="top">
        <button class="icon-btn menu-btn c-indigo" aria-label="Buka menu" @click="navOpen = !navOpen"><Icon name="menu" /></button>
        <form class="search" role="search" @submit.prevent="search"><span class="muted"><Icon name="search" /></span><input v-model="q" type="search" placeholder="Cari risiko (kode/nama)…" aria-label="Pencarian risiko"></form>
        <div class="top-ctx">
          <button class="icon-btn c-indigo" aria-label="Ganti tema terang/gelap" @click="toggleTheme"><Icon name="moon" /></button>
          <Link class="icon-btn c-orange" href="/alerts" aria-label="Peringatan dini"><Icon name="bell" /><span v-if="badges.alerts" class="dot">{{ badges.alerts }}</span></Link>
          <Link class="icon-btn c-violet" href="/ai" aria-label="AI Risk Assistant"><Icon name="spark" /></Link>
          <button type="button" class="user u-btn" aria-haspopup="menu" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen"><span class="avatar">{{ fmt.initials(user?.name) }}</span><span class="u-txt"><b>{{ user?.name }}</b><br><span class="muted">{{ user?.role_label }}</span></span></button>
        </div>
      </header>
      <main class="content" id="view" tabindex="-1"><slot /></main>
    </div>
    <div v-if="navOpen" class="scrim clear" @click="navOpen = false"></div>
    <div v-if="menuOpen">
      <div class="scrim clear" @click="menuOpen = false"></div>
      <div class="umenu" role="menu">
        <div class="um-h"><span class="avatar">{{ fmt.initials(user?.name) }}</span><div><b>{{ user?.name }}</b><div class="muted" style="font-size:12px">{{ user?.role_label }} · {{ user?.unit || 'Seluruh organisasi' }}</div><div class="mono muted" style="font-size:11.5px">{{ user?.email }}</div></div></div>
        <Link class="um-i" role="menuitem" href="/profile"><Icon name="users" />Profil & preferensi</Link>
        <Link class="um-i" role="menuitem" href="/password"><Icon name="lock" />Ganti kata sandi</Link>
        <button type="button" class="um-i" role="menuitem" @click="toggleTheme"><Icon name="moon" />Ganti tema</button>
        <button type="button" class="um-i danger" role="menuitem" @click="logout"><Icon name="x" />Keluar</button>
      </div>
    </div>
  </div>
</template>
