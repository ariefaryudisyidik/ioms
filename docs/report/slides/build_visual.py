#!/usr/bin/env python3
"""Builds docs/report/IOMS-Presentation.pdf (16:9) from HTML via headless Chrome.

    python3 docs/report/slides/build_visual.py

Icons: Lucide (ISC), see slides/icons/LICENSE. Screenshots: slides/img/.
"""
import re, subprocess, sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / 'IOMS-Presentation.pdf'
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'


def ic(name, size=28):
    svg = (HERE / 'icons' / f'{name}.svg').read_text()
    svg = re.sub(r'<!--.*?-->\s*', '', svg, flags=re.S)
    return re.sub(r'<svg[^>]*>', lambda m: f'<svg class="ic" width="{size}" height="{size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">', svg, count=1)


def browser(img, url, cls=''):
    return f'<div class="browser {cls}"><div class="bar"><i></i><i></i><i></i><span>{url}</span></div><img src="img/{img}.jpg"></div>'


def foot(n, total=12):
    return f'<div class="foot"><span>IOMS · Final Project · 7 Oktober 2026</span><span>{n:02d} / {total}</span></div>'


CSS = """
@page { size: 1280px 720px; margin: 0; }
:root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --blue:#2563eb; --blue2:#1e40af; --soft:#eff4ff; --ok:#16a34a; --okbg:#e8f7ee; --warn:#d97706; --warnbg:#fef3e2; --bad:#dc2626; --badbg:#fdeaea; --navy:#0b1b3f; }
* { box-sizing:border-box; }
html,body { margin:0; padding:0; font-family:"Helvetica Neue",Helvetica,Arial,sans-serif; color:var(--ink); -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.slide { width:1280px; height:720px; padding:56px 72px; position:relative; overflow:hidden; page-break-after:always; background:#fff; }
.slide:last-child { page-break-after:auto; }
.dark { background:linear-gradient(135deg,#0b1b3f 0%,#16327a 60%,#2563eb 140%); color:#fff; }
.eyebrow { display:inline-flex; align-items:center; gap:8px; font-size:13px; letter-spacing:.14em; text-transform:uppercase; font-weight:700; color:var(--blue); margin-bottom:10px; }
.dark .eyebrow { color:#93b4ff; }
h1 { font-size:58px; line-height:1.05; margin:0 0 18px; letter-spacing:-.02em; }
h2 { font-size:38px; line-height:1.12; margin:0 0 26px; letter-spacing:-.015em; }
h3 { font-size:19px; margin:0 0 6px; }
p { margin:0; font-size:18px; line-height:1.5; color:var(--muted); }
.dark p { color:#c7d6f5; }
.foot { position:absolute; left:72px; right:72px; bottom:24px; display:flex; justify-content:space-between; font-size:12px; color:var(--muted); }
.ic { flex-shrink:0; }
.row { display:flex; gap:24px; }
.grow { flex:1; min-width:0; }
.card { border:1.5px solid var(--line); border-radius:16px; padding:20px 22px; background:#fff; }
.card.soft { background:var(--soft); border-color:transparent; }
.card.ok { background:var(--okbg); border-color:transparent; }
.card.warn { background:var(--warnbg); border-color:transparent; }
.card.bad { background:var(--badbg); border-color:transparent; }
.badge-ic { width:48px; height:48px; border-radius:12px; background:var(--soft); color:var(--blue); display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
.soft .badge-ic { background:#dbe7ff; color:var(--blue); } .ok .badge-ic { background:#cdeedb; color:var(--ok); } .warn .badge-ic { background:#fde3bd; color:var(--warn); } .bad .badge-ic { background:#f9cccc; color:var(--bad); }
.grid3 { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
.grid4 { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
.chip { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:99px; font-size:14px; font-weight:600; background:var(--soft); color:var(--blue2); margin:0 6px 8px 0; }
.chip.ok { background:var(--okbg); color:#166534; }
.browser { border-radius:12px; overflow:hidden; box-shadow:0 18px 50px rgba(15,23,42,.28); background:#fff; border:1px solid #cbd5e1; }
.browser .bar { height:30px; background:#e8edf5; display:flex; align-items:center; gap:6px; padding:0 12px; }
.browser .bar i { width:10px; height:10px; border-radius:50%; background:#cbd5e1; display:block; }
.browser .bar span { margin-left:12px; font-size:12px; color:#64748b; background:#fff; border-radius:99px; padding:3px 14px; }
.browser img { display:block; width:100%; }
.stepper { display:flex; align-items:center; gap:10px; margin:6px 0 18px; }
.step { padding:9px 16px; border-radius:99px; font-weight:700; font-size:15px; background:var(--soft); color:var(--blue2); }
.step.d { background:#e5e7eb; color:#374151; } .step.o { background:#dbe7ff; } .step.p { background:var(--warnbg); color:#92400e; } .step.r { background:var(--okbg); color:#166534; } .step.x { background:var(--badbg); color:#991b1b; }
.arr { color:#94a3b8; }
.num { display:inline-flex; width:30px; height:30px; border-radius:50%; background:var(--blue); color:#fff; font-weight:700; font-size:15px; align-items:center; justify-content:center; flex-shrink:0; }
.steps li { list-style:none; display:flex; gap:12px; margin-bottom:14px; font-size:17px; line-height:1.4; align-items:flex-start; }
.steps { padding:0; margin:0; }
code { font-family:Menlo,Monaco,monospace; background:#eef2f7; padding:1px 6px; border-radius:5px; font-size:.86em; color:#0f172a; }
pre { margin:0; background:#0b1b3f; color:#e6edfb; border-radius:14px; padding:22px 26px; font-family:Menlo,Monaco,monospace; font-size:15px; line-height:1.6; }
pre .c { color:#7f8fb3; } pre .k { color:#7aa2ff; } pre .g { color:#6ee7a8; } pre .r { color:#ff9b9b; }
.chain { display:flex; align-items:center; gap:10px; }
.node { flex:1; border-radius:14px; padding:16px; background:var(--soft); text-align:center; }
.node b { display:block; font-size:17px; margin-top:8px; } .node span { font-size:13px; color:var(--muted); }
.node .ic { color:var(--blue); }
.stat { border-radius:18px; padding:24px; background:var(--okbg); }
.stat .big { font-size:64px; font-weight:800; letter-spacing:-.03em; color:#14532d; line-height:1; }
.stat .lbl { font-size:15px; color:#3f6212; margin-top:8px; line-height:1.35; }
table { border-collapse:collapse; width:100%; font-size:16px; }
th { text-align:left; font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); padding:8px 12px; border-bottom:2px solid var(--line); }
td { padding:10px 12px; border-bottom:1px solid var(--line); }
.after { font-weight:800; color:#166534; }
.pts { margin:8px 0 0; padding-left:20px; font-size:17px; line-height:1.45; }
.pts li { margin-bottom:10px; }
.pts li span { font-size:15px; opacity:.85; }
.slide.flex { display:flex; flex-direction:column; }
.fill { flex:1; display:flex; flex-direction:column; justify-content:center; padding-bottom:34px; min-height:0; }
.card p { font-size:17px !important; }
.card h3 { font-size:21px; }
.steps li { font-size:19px; }
.clip { max-height:470px; overflow:hidden; border-radius:12px; }
.clip .browser { border-radius:12px 12px 0 0; }
"""


def slides():
    s = []
    # 1 cover
    s.append(f"""<section class="slide dark">
<div style="position:absolute;right:-70px;top:96px;width:640px;transform:rotate(-3deg)">{browser('dashboard','localhost:8080/dashboard')}</div>
<div style="width:600px;margin-top:70px"><div class="eyebrow">{ic('boxes',18)} Intermediate Programmer · Final Project</div>
<h1>Inventory &amp; Order Management System</h1>
<p style="font-size:21px">Stok multi-gudang yang konsisten, termasuk saat ada proses yang berjalan bersamaan.</p>
<div style="margin-top:46px;display:flex;gap:30px;font-size:14px;color:#c7d6f5;white-space:nowrap"><div>DISUSUN OLEH<br><b style="color:#fff;font-size:16px">Arief Aryudi Syidik</b></div><div>PROGRAM<br><b style="color:#fff;font-size:16px">PT Neuronworks Indonesia</b></div><div>SESI<br><b style="color:#fff;font-size:16px">Rabu, 7 Oktober 2026</b></div></div></div></section>""")
    # 2 problem + roles
    s.append(f"""<section class="slide"><div class="eyebrow">01 · Pembukaan</div><h2>Satu sistem, tiga peran, stok tercatat konsisten</h2>
<p style="max-width:900px;margin-bottom:28px">Tim gudang dan sales mencatat pembelian, penjualan, dan stok di beberapa gudang. Angka stoknya perlu tetap konsisten, termasuk saat dua proses berjalan bersamaan.</p>
<div class="grid3">
<div class="card soft"><div class="badge-ic">{ic('user-cog',26)}</div><h3>Admin</h3><p style="font-size:16px">Mengelola master data dan user. Satu-satunya yang boleh menyetujui SO, tapi tidak untuk SO buatannya sendiri.</p></div>
<div class="card soft"><div class="badge-ic">{ic('shopping-cart',26)}</div><h3>Sales</h3><p style="font-size:16px">Membuat dan mengajukan SO, hanya bisa melihat SO miliknya sendiri, dan bisa cek stok langsung.</p></div>
<div class="card soft"><div class="badge-ic">{ic('warehouse',26)}</div><h3>Warehouse Staff</h3><p style="font-size:16px">Memproses barang masuk (PO) dan barang keluar (SO); stok dan ledger ikut terupdate dalam satu transaksi.</p></div></div>
<div style="margin-top:26px"><span class="chip ok">{ic('check',16)} Fitur wajib tersedia</span><span class="chip">PO + goods receipt</span><span class="chip">SO + approval + goods issue</span><span class="chip">Stock ledger</span><span class="chip">Laporan CSV</span><span class="chip">JSON endpoint</span><span class="chip">Dashboard per role</span></div>
{foot(2)}</section>""")
    # 3 stack
    s.append(f"""<section class="slide"><div class="eyebrow">01 · Pembukaan</div><h2>Teknologi yang digunakan</h2>
<div class="grid4">
<div class="card"><div class="badge-ic">{ic('code-xml',26)}</div><h3>PHP 8.2 native</h3><p style="font-size:15px">OOP berlapis, tanpa framework dan ORM; PDO prepared statement.</p></div>
<div class="card"><div class="badge-ic">{ic('database',26)}</div><h3>MySQL 8</h3><p style="font-size:15px">Relasi, constraint, index, transaksi eksplisit.</p></div>
<div class="card"><div class="badge-ic">{ic('route',26)}</div><h3>Vanilla JS + Fetch</h3><p style="font-size:15px">HTML semantik, CSS buatan sendiri, ikon Lucide (SVG lokal).</p></div>
<div class="card"><div class="badge-ic">{ic('container',26)}</div><h3>Docker Compose</h3><p style="font-size:15px">App (Apache non-root) + MySQL; schema dan seed jalan otomatis.</p></div></div>
{foot(3)}</section>""")
    # 4 demo A
    s.append(f"""<section class="slide"><div class="eyebrow">02 · Demo A</div><h2>Purchase Order → penerimaan barang</h2>
<div class="row"><div style="width:470px">
<div class="stepper"><span class="step d">Draft</span><span class="arr">›</span><span class="step o">Ordered</span><span class="arr">›</span><span class="step p">Partially</span><span class="arr">›</span><span class="step r">Received</span></div>
<ul class="steps"><li><span class="num">1</span><span>Warehouse membuat PO (SKU-0010, qty 10) lalu klik <b>Order</b>.</span></li>
<li><span class="num">2</span><span><b>Receive</b> sebagian (4) → status PartiallyReceived.</span></li>
<li><span class="num">3</span><span>Stok produk naik 4 dan muncul baris <code>stock_ledger</code> tipe Receipt, semuanya dalam <b>satu transaksi</b>.</span></li></ul>
<div class="card soft" style="margin-top:6px"><p style="font-size:15px;color:#1e3a8a">Perubahan data ditampilkan langsung dari database.</p></div></div>
<div class="grow">{browser('po','localhost:8080/purchase-orders')}</div></div>{foot(4)}</section>""")
    # 5 demo B
    s.append(f"""<section class="slide"><div class="eyebrow">02 · Demo B</div><h2>Sales Order, approval, dan validasi stok</h2>
<div class="row"><div style="width:470px"><ul class="steps">
<li><span class="num">1</span><span>Sales membuat SO; stok per gudang terisi lewat <b>Fetch API</b> tanpa reload.</span></li>
<li><span class="num">2</span><span>Sales mencoba approve → server membalas <b>403</b>. Admin approve.</span></li>
<li><span class="num">3</span><span>Warehouse <b>Fulfill</b> dengan qty melebihi stok → ditolak.</span></li>
<li><span class="num">4</span><span>Stok dan ledger <b>tidak berubah</b> (rollback).</span></li></ul>
<div class="card bad" style="display:flex;gap:12px;align-items:center;margin-top:8px">{ic('shield-check',28)}<p style="font-size:15px;color:#7f1d1d">Pemisahan tugas dicek di sisi server.</p></div></div>
<div class="grow">{browser('so-create','localhost:8080/sales-orders/create')}</div></div>{foot(5)}</section>""")
    # 6 architecture
    s.append(f"""<section class="slide"><div class="eyebrow">03 · Implementasi teknis</div><h2>Arsitektur berlapis dengan Dependency Inversion</h2>
<div class="chain">
<div class="node">{ic('route',30)}<b>Router</b><span>regex + {{param}}</span></div><span class="arr">›</span>
<div class="node">{ic('layers',30)}<b>Controller</b><span>HTTP + otorisasi</span></div><span class="arr">›</span>
<div class="node">{ic('git-branch',30)}<b>Service</b><span>aturan bisnis</span></div><span class="arr">›</span>
<div class="node">{ic('database',30)}<b>Repository</b><span>interface + MySQL</span></div><span class="arr">›</span>
<div class="node">{ic('package',30)}<b>View</b><span>template PHP</span></div></div>
<div class="grid3" style="margin-top:34px">
<div class="card"><h3>Service hanya tahu interface</h3><p style="font-size:15px">Diuji dengan <code>InMemory*Repository</code>, tanpa database (ADR-001).</p></div>
<div class="card"><h3>Service bebas PDO</h3><p style="font-size:15px"><code>TransactionManagerInterface</code> membungkus commit/rollback (ADR-005), dijaga <code>ArchitectureTest</code>.</p></div>
<div class="card"><h3>Error terpusat</h3><p style="font-size:15px">Exception domain diubah jadi 422 / 403 / 409 / 500 tanpa membocorkan detail.</p></div></div>{foot(6)}</section>""")
    # 7 oversell
    s.append(f"""<section class="slide"><div class="eyebrow">03 · Implementasi teknis</div><h2>Menjaga konsistensi stok</h2>
<div class="row"><div style="width:600px"><pre><span class="c">// SalesOrderService::fulfill()</span>
$this-&gt;transactions-&gt;<span class="k">run</span>(<span class="k">function</span> () {{
  <span class="k">foreach</span> ($items <span class="k">as</span> $item) {{
    $avail = $stocks-&gt;<span class="g">lockForUpdate</span>(...);
    <span class="c">// SELECT quantity ... FOR UPDATE</span>
    <span class="k">if</span> ($avail &lt; $item-&gt;qty)
      <span class="k">throw new</span> <span class="r">InsufficientStockException</span>;
  }}
  <span class="c">// decrement + stock_ledger + status</span>
}}); <span class="c">// sukses: commit · gagal: rollBack</span></pre></div>
<div class="grow"><ul class="steps">
<li><span class="num">1</span><span><b>Row lock</b> InnoDB: dua fulfill untuk produk dan gudang yang sama harus antre.</span></li>
<li><span class="num">2</span><span>Stok dicek <b>setelah</b> lock, jadi hasilnya pasti yang terbaru.</span></li>
<li><span class="num">3</span><span>Satu item kurang → <b>seluruh transaksi dibatalkan</b>.</span></li>
<li><span class="num">4</span><span>Pendekatan pessimistic lock dipilih agar pengecekan dan pengurangan stok konsisten (ADR-002).</span></li></ul></div></div>{foot(7)}</section>""")
    # 8 security
    sec = [('lock','CSRF','Token per sesi di semua form POST'),('user-cog','Sesi &amp; role','HttpOnly, SameSite, user divalidasi ulang tiap request'),('shield-check','Brute force','Login dibatasi: 5 kali gagal per akun+IP'),('eye','IDOR','Sales hanya bisa melihat SO miliknya'),('database','Injeksi','Prepared statement, output di-escape + CSP'),('container','Container','Non-root, MySQL hanya di 127.0.0.1')]
    cards = ''.join(f'<div class="card"><div class="badge-ic">{ic(i,24)}</div><h3>{t}</h3><p style="font-size:15px">{d}</p></div>' for i,t,d in sec)
    s.append(f"""<section class="slide"><div class="eyebrow">03 · Implementasi teknis</div><h2>Keamanan: kontrol yang diterapkan</h2><div class="grid3">{cards}</div>
<p style="margin-top:22px;font-size:15px">Pengembangan berikutnya: rate limiting API dan MFA. Rincian: <code>docs/quality/security-review.md</code>.</p>{foot(8)}</section>""")
    # 9 dashboard visual
    s.append(f"""<section class="slide"><div class="eyebrow">03 · Implementasi teknis</div><h2>Dashboard per role dan antarmuka konsisten</h2>
<div class="row"><div class="grow clip">{browser('dashboard','localhost:8080/dashboard')}</div>
<div style="width:300px"><ul class="steps"><li><span class="num">1</span><span>Kartu ringkasan berbeda untuk Admin, Sales, dan Warehouse.</span></li><li><span class="num">2</span><span>Daftar <b>Low Stock</b> dihitung dari stok total vs reorder point.</span></li><li><span class="num">3</span><span>Sidebar memuat info user dan Logout; ikon Lucide inline (SVG lokal), tanpa library JS atau CDN.</span></li></ul></div></div>{foot(9)}</section>""")
    # 10 quality
    s.append(f"""<section class="slide"><div class="eyebrow">04 · Bukti kualitas</div><h2>Test dan SonarQube</h2>
<div class="grid4"><div class="stat"><div class="big">316</div><div class="lbl">test lulus<br>Unit 170 · Integration 9 · E2E 137</div></div>
<div class="stat"><div class="big">100%</div><div class="lbl">line coverage<br>Unit + Integration + E2E (HTTP)</div></div>
<div class="stat"><div class="big">0</div><div class="lbl">bug, vulnerability,<br>smell, hotspot</div></div>
<div class="stat"><div class="big">0%</div><div class="lbl">duplikasi kode<br>quality gate: Passed</div></div></div>
<table style="margin-top:30px;max-width:760px"><tr><th>Metrik</th><th>Scan awal</th><th>Akhir</th></tr>
<tr><td>Open Issues</td><td>192</td><td class="after">0</td></tr><tr><td>Coverage</td><td>21,1%</td><td class="after">100%</td></tr><tr><td>Duplications</td><td>8,1%</td><td class="after">0%</td></tr></table>
<p style="margin-top:14px;font-size:14px">Coverage yang diukur adalah <i>line coverage</i>. PHPStan level 5 dan PHPCS PSR-12: 0 error.</p>{foot(10)}</section>""")
    # 11 refleksi
    s.append(f"""<section class="slide"><div class="eyebrow">05 · Refleksi</div><h2>Kendala, solusi, dan rencana pengembangan</h2>
<div class="grid3" style="align-items:stretch">
<div class="card soft"><div class="badge-ic">{ic('hourglass',24)}</div><h3>Kendala &amp; solusi</h3>
<ul class="pts" style="color:#1e3a8a">
<li><b>Waktu tidak sinkron:</b> database dan aplikasi memakai UTC, selisih 7 jam dari jam lokal.<br><span>Solusi: zona waktu PHP dan MySQL diatur ke WIB (GMT+7).</span></li>
<li><b>Library PHP hilang</b> saat aplikasi dijalankan dari clone baru.<br><span>Solusi: kode dan library diambil dari image Docker.</span></li>
</ul></div>
<div class="card warn"><div class="badge-ic">{ic('triangle-alert',24)}</div><h3>Pengembangan lanjutan</h3>
<ul class="pts" style="color:#7c4a0a"><li>Rate limiting dan token untuk API</li><li>CI/CD</li></ul></div>
<div class="card ok"><div class="badge-ic">{ic('route',24)}</div><h3>Prioritas</h3>
<ol class="pts" style="color:#14532d"><li>Rate limiting API</li><li>MFA dan token API</li></ol></div></div>{foot(11)}</section>""")
    # 12 QA
    s.append(f"""<section class="slide dark"><div style="position:absolute;right:70px;top:120px;width:470px;transform:rotate(2deg)">{browser('login','localhost:8080/login')}</div>
<div style="margin-top:170px"><h1 style="font-size:96px">Terima kasih</h1>
<div style="margin-top:34px;display:flex;gap:44px;font-size:14px;color:#9db7f0;white-space:nowrap"><div>DISUSUN OLEH<br><b style="color:#fff;font-size:19px">Arief Aryudi Syidik</b></div></div></div></section>""")
    return s


def wrap(sec):
    if '<h2>' not in sec or '<div class="foot">' not in sec:
        return sec
    sec = sec.replace('<section class="slide">', '<section class="slide flex">', 1)
    sec = sec.replace('</h2>', '</h2><div class="fill">', 1)
    return sec.replace('<div class="foot">', '</div><div class="foot">', 1)


html = f'<!doctype html><html lang="id"><head><meta charset="utf-8"><title>IOMS - Presentasi Final Project</title><style>{CSS}</style></head><body>{"".join(wrap(x) for x in slides())}</body></html>'
page = HERE / '.visual.html'
page.write_text(html)
subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', f'--print-to-pdf={OUT}', f'file://{page}'], check=True, capture_output=True)
page.unlink()
print('wrote', OUT)
