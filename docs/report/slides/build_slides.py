#!/usr/bin/env python3
"""Builds docs/report/IOMS-Presentation-2026-10-07.pdf (16:9, 12 slides).

    python3 docs/report/slides/build_slides.py

Needs reportlab and the macOS system fonts (Arial, Monaco).
"""
from pathlib import Path

from reportlab.lib.colors import HexColor, white
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.pdfmetrics import registerFontFamily
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph, Table, TableStyle

FONTS = Path('/System/Library/Fonts/Supplemental')
pdfmetrics.registerFont(TTFont('Sans', str(FONTS / 'Arial.ttf')))
pdfmetrics.registerFont(TTFont('Sans-Bold', str(FONTS / 'Arial Bold.ttf')))
pdfmetrics.registerFont(TTFont('Sans-Italic', str(FONTS / 'Arial Italic.ttf')))
pdfmetrics.registerFont(TTFont('Sans-BoldItalic', str(FONTS / 'Arial Bold Italic.ttf')))
registerFontFamily('Sans', normal='Sans', bold='Sans-Bold', italic='Sans-Italic', boldItalic='Sans-BoldItalic')
pdfmetrics.registerFont(TTFont('Mono', '/System/Library/Fonts/Monaco.ttf'))

W, H = 1280, 720
MX = 76
INK = HexColor('#14213d')
MUTED = HexColor('#5b6577')
LINE = HexColor('#dfe4ec')
ACCENT = HexColor('#1d4ed8')
SOFT = HexColor('#e8eefc')
OK = HexColor('#e6f4ea')
WARN = HexColor('#fdf1e0')
CODE_BG = HexColor('#0f1b33')

OUT = Path(__file__).resolve().parent.parent / 'IOMS-Presentation-2026-10-07.pdf'


def style(size=17, color=INK, bold=False, leading=None, align=0, name='Sans'):
    return ParagraphStyle(
        'x', fontName='Sans-Bold' if bold else name, fontSize=size,
        leading=leading or size * 1.38, textColor=color, alignment=align,
    )


def code(text):
    return f'<font name="Mono" backColor="#eef1f6" size="15">&nbsp;{text}&nbsp;</font>'


class Deck:
    def __init__(self):
        self.c = canvas.Canvas(str(OUT), pagesize=(W, H))
        self.c.setTitle('IOMS - Presentasi Final Project')
        self.c.setAuthor('Arief Aryudi Syidik')
        self.page = 0

    # ---- primitives -------------------------------------------------
    def para(self, text, x, top, width, st):
        p = Paragraph(text, st)
        _, h = p.wrap(width, 10_000)
        p.drawOn(self.c, x, top - h)
        return h

    def bullets(self, items, x, top, width, size=17, gap=9, color=INK):
        y = top
        for it in items:
            st = ParagraphStyle('b', fontName='Sans', fontSize=size, leading=size * 1.38,
                                textColor=color, leftIndent=18, bulletIndent=0)
            p = Paragraph(it, st, bulletText='•')
            _, h = p.wrap(width, 10_000)
            p.drawOn(self.c, x, y - h)
            y -= h + gap
        return top - y

    def card(self, x, top, w, h, fill=None, stroke=LINE):
        c = self.c
        c.setLineWidth(1.4)
        c.setStrokeColor(stroke if fill is None else fill)
        c.setFillColor(fill or white)
        c.roundRect(x, top - h, w, h, 14, stroke=1, fill=1)

    def pill_num(self, n, x, y_center):
        c = self.c
        c.setFillColor(ACCENT)
        c.circle(x + 15, y_center, 15, stroke=0, fill=1)
        c.setFillColor(white)
        c.setFont('Sans-Bold', 15)
        c.drawCentredString(x + 15, y_center - 5.5, str(n))

    def table(self, rows, x, top, widths, size=15, header=True, center_cols=()):
        data = []
        for ri, row in enumerate(rows):
            line = []
            for ci, cell in enumerate(row):
                head = header and ri == 0
                st = ParagraphStyle(
                    't', fontName='Sans-Bold' if head else 'Sans', fontSize=12.5 if head else size,
                    leading=(12.5 if head else size) * 1.3, textColor=MUTED if head else INK,
                    alignment=1 if ci in center_cols else 0,
                )
                line.append(Paragraph(cell.upper() if head else cell, st))
            data.append(line)
        t = Table(data, colWidths=widths)
        t.setStyle(TableStyle([
            ('LINEBELOW', (0, 0), (-1, -1), 1.2, LINE),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('TOPPADDING', (0, 0), (-1, -1), 9),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 9),
            ('LEFTPADDING', (0, 0), (-1, -1), 10),
            ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ]))
        _, h = t.wrap(sum(widths), 10_000)
        t.drawOn(self.c, x, top - h)
        return h

    # ---- slide chrome -------------------------------------------------
    def new(self, tag, title, plain=False):
        if self.page:
            self.c.showPage()
        self.page += 1
        c = self.c
        if plain:
            c.setFillColor(HexColor('#eef3ff'))
            c.rect(0, 0, W, H, stroke=0, fill=1)
            return
        c.setFillColor(ACCENT)
        c.setFont('Sans-Bold', 13)
        c.drawString(MX, H - 62, tag.upper().replace('', ' ').strip(' ') if False else tag.upper())
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 33)
        c.drawString(MX, H - 108, title)
        c.setFillColor(MUTED)
        c.setFont('Sans', 11)
        c.drawString(MX, 26, 'IOMS · Final Project')
        c.drawRightString(W - MX, 26, f'{self.page:02d}')

    def save(self):
        self.c.showPage()
        self.c.save()


def main():
    d = Deck()
    c = d.c
    top = H - 150  # content top for normal slides

    # 1 ── title ──────────────────────────────────────────────────────
    d.new('', '', plain=True)
    c.setFillColor(ACCENT)
    c.setFont('Sans-Bold', 14)
    c.drawString(MX, 520, 'INTERMEDIATE PROGRAMMER · FINAL PROJECT')
    c.setFillColor(INK)
    c.setFont('Sans-Bold', 56)
    c.drawString(MX, 450, 'Inventory & Order')
    c.drawString(MX, 385, 'Management System')
    d.para('Aplikasi web multi-gudang dengan tiga peran, berlapis (Controller → Service → Repository), '
           'dan integritas stok yang terjaga saat proses berjalan bersamaan.',
           MX, 340, 880, style(21, MUTED, leading=30))
    for i, (lbl, val) in enumerate([('Disusun oleh', 'Arief Aryudi Syidik'),
                                    ('Program', 'PT Neuronworks Indonesia'),
                                    ('Sesi', 'Rabu, 7 Oktober 2026')]):
        x = MX + i * 330
        c.setFillColor(MUTED)
        c.setFont('Sans', 13)
        c.drawString(x, 190, lbl.upper())
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 18)
        c.drawString(x, 162, val)

    # 2 ── problem ────────────────────────────────────────────────────
    d.new('01 · Pembukaan', 'Masalah dan pengguna')
    hh = d.para('Tim gudang dan sales perlu mencatat pembelian dari supplier dan penjualan ke customer di beberapa '
                'gudang, dengan <b>angka stok yang bisa dipertanggungjawabkan</b>, termasuk ketika dua proses '
                'berjalan bersamaan.', MX, top, 540, style(21, leading=30))
    y = top - hh - 22
    d.card(MX, y, 540, 175, OK)
    d.para('<b>Status penyelesaian</b>', MX + 20, y - 16, 500, style(19))
    d.para('Semua fitur wajib selesai: master data, PO, SO dengan approval, goods receipt/issue, perpindahan stok, '
           'stock ledger, laporan CSV, dashboard, dan endpoint JSON.', MX + 20, y - 48, 500, style(17, leading=24))
    d.para('Kontribusi saya: perancangan dan implementasi proyek. Sebagian besar kode, test, dan dokumentasi dibuat '
           'dengan bantuan Claude Code; saya bertanggung jawab memahami dan menjelaskannya (lihat slide terakhir).',
           MX, y - 195, 540, style(15, MUTED, leading=21))
    rows = [['Aktivitas', 'Admin', 'Sales', 'Gudang'],
            ['Buat dan ajukan SO', 'Ya', 'Miliknya', 'Tidak'],
            ['Setujui / tolak SO', 'Ya*', 'Tidak', 'Tidak'],
            ['Goods issue (SO)', 'Ya', 'Tidak', 'Ya'],
            ['Goods receipt (PO)', 'Ya', 'Tidak', 'Ya'],
            ['Kelola user', 'Ya', 'Tidak', 'Tidak']]
    d.table(rows, 650, top, [240, 100, 120, 118], size=18, center_cols=(1, 2, 3))
    d.para('* Bukan untuk SO yang ia buat sendiri. Aturan ditegakkan di server, bukan hanya disembunyikan di UI.',
           650, top - 345, 560, style(14, MUTED, leading=19))

    # 3 ── demo ───────────────────────────────────────────────────────
    d.new('02 · Demo alur utama', 'Dua alur yang mewakili requirement')
    for i, (title, steps) in enumerate([
        ('A · Purchase Order → penerimaan barang', [
            'Warehouse membuat PO (Draft) lalu <b>Order</b>',
            '<b>Receive</b> sebagian → PartiallyReceived',
            f'Stok naik dan baris {code("stock_ledger")} tipe Receipt, dalam <b>satu transaksi</b>']),
        ('B · Sales Order → anti-oversell', [
            'Sales membuat SO; stok tampil lewat <b>Fetch API</b>; submit',
            'Sales tidak bisa approve (server <b>403</b>); Admin approve',
            'Warehouse <b>Fulfill</b> dengan qty melebihi stok → ditolak; stok dan ledger <b>tidak berubah</b>'])]):
        x = MX + i * 568
        d.card(x, top, 540, 340, SOFT)
        d.para(f'<b>{title}</b>', x + 24, top - 24, 500, style(21))
        yy = top - 90
        for n, s in enumerate(steps, 1):
            p = Paragraph(s, style(19, leading=26))
            _, h = p.wrap(450, 10_000)
            d.pill_num(n, x + 22, yy - 14)
            p.drawOn(c, x + 64, yy - h + 2)
            yy -= max(h, 30) + 26
    d.para('Bukti perubahan data ditunjukkan langsung dari database (stok dan ' + code('stock_ledger') +
           ') sebelum dan sesudah.', MX, top - 372, 1100, style(16, MUTED))

    # 4 ── architecture ───────────────────────────────────────────────
    d.new('03 · Implementasi teknis', 'Arsitektur berlapis dan alur request')
    boxes = [('index.php', 'front controller'), ('Router', 'regex + {param}, args by nama'),
             ('Controller', 'HTTP + otorisasi'), ('Service', 'aturan bisnis, transaksi'),
             ('Repository', 'SQL prepared statement'), ('View', 'template PHP, e()')]
    bw, gap = 160, 30
    for i, (t, s) in enumerate(boxes):
        x = MX + i * (bw + gap)
        d.card(x, top, bw, 88, SOFT)
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 16)
        c.drawString(x + 12, top - 28, t)
        d.para(s, x + 12, top - 38, bw - 20, style(12.5, MUTED, leading=15))
        if i < len(boxes) - 1:
            c.setFillColor(ACCENT)
            c.setFont('Sans-Bold', 22)
            c.drawCentredString(x + bw + gap / 2, top - 52, '→')
    layers = [('Controller', 'app/Controller · CrudController untuk 5 master data', '#e8eefc'),
              ('Service', 'app/Service · validasi, state machine, transaksi', '#dbe6ff'),
              ('Repository interface', 'Dependency Inversion (ADR-001)', '#cddcff'),
              ('MySQL / In-Memory', 'runtime sungguhan / test double', '#bcd0fb')]
    ly = top - 115
    for t, s, col in layers:
        d.card(MX, ly, 560, 70, HexColor(col))
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 16)
        c.drawString(MX + 18, ly - 42, t)
        d.para(s, MX + 215, ly - 27, 335, style(14, MUTED, leading=18))
        ly -= 84
    d.bullets([
        'Service hanya tahu <b>interface</b>, jadi diuji tanpa database.',
        'Tanpa framework dan ORM; PDO prepared statement di semua query.',
        f'Error domain dipetakan terpusat di {code("Controller::handle")}: 422 / 403 / 409 / 500, tanpa membocorkan detail.',
        f'Diagram: {code("docs/architecture/class-diagram-as-built.md")}'],
        690, top - 128, 510, size=19, gap=12)

    # 5 ── stock integrity ────────────────────────────────────────────
    d.new('03 · Implementasi teknis', 'Integritas stok: tidak ada oversell')
    d.card(MX, top, 570, 390, CODE_BG, CODE_BG)
    lines = [
        ('c', '// SalesOrderService::fulfill()'),
        ('', '$pdo->beginTransaction();'),
        ('', 'foreach ($items as $item) {'),
        ('', '  $available = $stocks->lockForUpdate(...);'),
        ('c', '  // SELECT quantity ... FOR UPDATE'),
        ('', '  if ($available < $item->qty) {'),
        ('', '    throw InsufficientStockException;'),
        ('', '  }'),
        ('', '}'),
        ('c', '// decrement + tulis stock_ledger'),
        ('', '$pdo->commit();   // gagal → rollBack()'),
    ]
    ly = top - 36
    for kind, text in lines:
        c.setFillColor(HexColor('#8a97b4') if kind == 'c' else HexColor('#e7ecf7'))
        c.setFont('Mono', 15)
        c.drawString(MX + 22, ly, text)
        ly -= 32
    d.bullets([
        '<b>Pessimistic row lock</b> InnoDB: dua goods issue pada produk dan gudang yang sama dipaksa antre.',
        'Cek stok dilakukan <b>setelah</b> lock, sehingga hasilnya tidak basi.',
        'Kekurangan di satu item <b>membatalkan seluruh transaksi</b>: tidak ada stok setengah berkurang.',
        'Terbukti lewat integration test terhadap MySQL asli dan test E2E.',
        f'Keputusan dan alternatif: {code("ADR-002")}'],
        680, top - 8, 525, size=19, gap=13)

    # 6 ── frontend / json / docker ───────────────────────────────────
    d.new('03 · Implementasi teknis', 'Frontend, JSON, dan Docker')
    cols = [
        ('Frontend', ['HTML semantik, CSS sendiri, responsif',
                      'Vanilla JS: validasi form, baris item dinamis',
                      'Semua form dilindungi token CSRF (otomatis)']),
        ('JSON API', [f'<b>Fetch API</b>: {code("GET /api/products/{sku}/availability")}',
                      '401 tanpa login, 404 SKU tidak ada',
                      'Error domain jadi JSON 422 / 403 / 409 / 500']),
        ('Docker', [f'{code("docker compose up --build")}: app + MySQL',
                    'Schema dan seed diimpor otomatis',
                    'Multi-stage, <b>non-root</b>, tanpa tool dev, healthcheck',
                    'MySQL hanya di <b>127.0.0.1</b>; diverifikasi dari clone bersih']),
    ]
    cw = 360
    for i, (t, items) in enumerate(cols):
        x = MX + i * (cw + 22)
        d.card(x, top, cw, 400)
        d.para(f'<b>{t}</b>', x + 20, top - 20, cw - 40, style(22))
        d.bullets(items, x + 20, top - 70, cw - 44, size=17.5, gap=13)

    # 7 ── security ────────────────────────────────────────────────────
    d.new('03 · Implementasi teknis', 'Keamanan: ancaman, kontrol, bukti')
    rows = [['Ancaman', 'Kontrol', 'Bukti'],
            ['CSRF', 'Token per-sesi di semua form POST, divalidasi di front controller', 'SecurityE2ETest'],
            ['Sesi dibajak / basi', f'HttpOnly, SameSite, timeout; user divalidasi ulang tiap request', 'Sesi, nonaktif, role'],
            ['Brute force login', 'Throttling DB (5 per akun, 20 per IP); respons waktu-konstan', 'Lockout E2E'],
            ['Akses objek (IDOR)', 'Sales hanya SO miliknya; tidak bisa setujui SO sendiri', 'Temuan diperbaiki'],
            ['Injeksi', 'Prepared statement; output di-escape + CSP; CSV dinetralkan', 'Audit + test'],
            ['Upload berbahaya', f'mime + {code("getimagesize")}, 2MB, nama acak, PHP mati di uploads', 'Diuji di Apache'],
            ['Container', 'Non-root, tanpa tool dev, header keamanan, MySQL localhost', 'Dicek di container']]
    h = d.table(rows, MX, top, [235, 640, 253], size=15.5)
    d.para('Sisa risiko (jujur): TLS dari reverse proxy, rate limiting API, MFA. Rincian: '
           + code('docs/quality/security-review.md') + ', ADR-004. SonarQube: 0 isu, 0 hotspot, security A.',
           MX, top - h - 22, 1128, style(14.5, MUTED))

    # 8 ── quality evidence ───────────────────────────────────────────
    d.new('04 · Bukti kualitas', 'Test dan SonarQube')
    stats = [('246', 'test lulus, 1.197 assertion\nUnit 124 · Integration 9 · E2E 113'),
             ('100%', 'line coverage\n(semua baris ter-cover)'),
             ('0', 'isu terbuka: bug, vulnerability,\nsmell, hotspot'),
             ('0%', 'duplikasi kode\nquality gate: Passed')]
    sw = 270
    for i, (big, lbl) in enumerate(stats):
        x = MX + i * (sw + 22)
        d.card(x, top, sw, 145, OK)
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 48)
        c.drawString(x + 22, top - 62, big)
        d.para(lbl.replace('\n', '<br/>'), x + 22, top - 82, sw - 30, style(13.5, MUTED, leading=17))
    rows = [['Metrik', 'Scan awal', 'Akhir'],
            ['Isu terbuka', '192', '<b>0</b>'],
            ['Reliability / Security / Maintainability', 'C / A / A', '<b>A / A / A</b>'],
            ['Coverage', '21,1%', '<b>100%</b>'],
            ['Duplikasi', '8,1%', '<b>0%</b>']]
    d.table(rows, MX, top - 175, [330, 120, 130], size=16.5, center_cols=(1, 2))
    d.bullets([
        'E2E menembak aplikasi lewat HTTP dan merekam coverage dari server (ADR-003), digabung dengan Unit + Integration.',
        'PHPStan level 5 dan PHPCS PSR-12: 0 error.',
        f'Dua pengecualian beralasan: {code("php:S2003")} (view/config) dan {code("php:S2092")} (flag Secure cookie aktif otomatis di HTTPS).'],
        MX + 630, top - 180, 500, size=18, gap=12)

    # 9 ── refactor table ─────────────────────────────────────────────
    d.new('04 · Bukti kualitas', 'Perbaikan utama yang dilakukan')
    rows = [['Temuan', 'Teknik', 'Hasil'],
            ['5 controller CRUD hampir identik', f'Template Method ({code("CrudController")})', '≈200 baris duplikat hilang'],
            ['Query builder PO/SO/ledger berulang', f'Extract Superclass ({code("AbstractMySqlRepository")})', 'SQL dibandingkan: identik'],
            ['HTML form/daftar disalin-tempel', f'Partial view ({code("views/partials/")})', 'Render dibandingkan: identik'],
            [f'43 parameter {code("$request")} tidak terpakai', 'Router memetakan argumen by nama (reflection)', 'Handler hanya deklarasi yang dipakai'],
            ['Method besar (kompleksitas, banyak return)', 'Extract Method', 'Di bawah ambang SonarQube'],
            ['Form tanpa label terasosiasi', f'Label membungkus kontrol, {code("aria-label")}', 'Isu aksesibilitas 0']]
    h = d.table(rows, MX, top, [370, 430, 328], size=17.5)
    d.para('Dokumentasi: class diagram initial vs as-built, ADR-001/002/003, ' + code('refactor-log.md') + ', ' +
           code('sonarqube-report.md') + '.', MX, top - h - 26, 1100, style(16, MUTED))

    # 10 ── reflection ────────────────────────────────────────────────
    d.new('05 · Refleksi', 'Kendala, keterbatasan, dan langkah berikutnya')
    d.card(MX, top, 560, 290, SOFT)
    d.para('<b>Kendala dan solusi</b>', MX + 22, top - 20, 520, style(22))
    d.bullets(['Stok benar saat konkuren → transaksi + row lock.',
               f'Clone bersih gagal jalan (bind mount menimpa {code("vendor/")}) → ketahuan saat verifikasi, diperbaiki.',
               'Coverage Controller/view awalnya 0% → test E2E lewat HTTP.'],
              MX + 22, top - 68, 520, size=18, gap=11)
    d.card(MX + 580, top, 548, 290, WARN)
    d.para('<b>Keterbatasan (jujur)</b>', MX + 602, top - 20, 500, style(22))
    d.bullets(['TLS perlu reverse proxy; rate limiting baru untuk login.',
               f'API memakai session cookie; {code("ApiController")} melewati Service.',
               '100% adalah <i>line</i> coverage, bukan semua kombinasi input.',
               'Tanpa CI/CD.'],
              MX + 602, top - 68, 505, size=18, gap=11)
    d.card(MX, top - 315, 1128, 110)
    d.para('<b>Prioritas berikutnya</b>', MX + 22, top - 333, 500, style(22))
    d.para('<b>1</b> Rate limiting API &nbsp;&nbsp;·&nbsp;&nbsp; <b>2</b> TLS + reverse proxy '
           '&nbsp;&nbsp;·&nbsp;&nbsp; <b>3</b> Audit log master data &nbsp;&nbsp;·&nbsp;&nbsp; <b>4</b> MFA &amp; token API',
           MX + 22, top - 372, 1090, style(19))

    # 11 ── AI disclosure ────────────────────────────────────────────
    d.new('Transparansi', 'Penggunaan AI')
    d.bullets([
        'Sebagian besar kode, test, dan dokumentasi dibuat dengan <b>Claude Code</b> (Anthropic).',
        'Subagent paralel dipakai untuk refactor duplikasi di worktree terpisah, lalu perilakunya '
        '<b>dibandingkan sebelum dan sesudah</b> dan diuji ulang.',
        'Saya memverifikasi hasil: build Docker dari clone bersih, test, SonarQube, dan smoke test semua route per role.'],
        MX, top, 600, size=21, gap=18)
    d.card(MX + 650, top, 478, 270, SOFT)
    d.para('<b>Tanggung jawab</b>', MX + 674, top - 22, 430, style(23))
    d.para(f'Saya bertanggung jawab memahami dan menjelaskan seluruh kode, termasuk {code("SalesOrderService::fulfill")}, '
           f'{code("Router")}, dan {code("CrudController")}.', MX + 674, top - 68, 430, style(18.5, leading=27))
    d.para(f'Rincian: {code("ai-usage-log.md")}', MX + 674, top - 212, 430, style(15.5, MUTED))

    # 12 ── Q&A ──────────────────────────────────────────────────────
    d.new('', '', plain=True)
    c.setFillColor(ACCENT)
    c.setFont('Sans-Bold', 14)
    c.drawString(MX, 470, 'TERIMA KASIH')
    c.setFillColor(INK)
    c.setFont('Sans-Bold', 60)
    c.drawString(MX, 400, 'Tanya jawab')
    d.para('Repository, README, ADR, class diagram, laporan SonarQube, dan laporan coverage siap ditelusuri '
           'langsung dari kode.', MX, 350, 860, style(20, MUTED, leading=29))
    for i, (lbl, val) in enumerate([('Akun demo', 'admin@ioms.test · Password123!'),
                                    ('Aplikasi', 'localhost:8080'), ('SonarQube', 'localhost:9001')]):
        x = MX + [0, 470, 720][i]
        c.setFillColor(MUTED)
        c.setFont('Sans', 13)
        c.drawString(x, 200, lbl.upper())
        c.setFillColor(INK)
        c.setFont('Sans-Bold', 18)
        c.drawString(x, 172, val)

    d.save()
    print(f'wrote {OUT}')


if __name__ == '__main__':
    main()
