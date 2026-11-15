<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $book->name }} {{ $chapter->number }} — Interlinear</title>
    <style>
        @page { size: Letter; margin: .55in .55in .65in; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1c1917; font-family: Georgia, serif; font-size: 9.5pt; }
        header { border-bottom: 1.5pt solid #292524; margin-bottom: 12pt; padding-bottom: 8pt; display:flex; justify-content:space-between; align-items:end; }
        h1 { margin: 0; font-size: 20pt; } .hebrew-title { direction:rtl; font-size:14pt; color:#57534e; }
        .passage { display:grid; grid-template-columns: 3fr 2fr; gap: 16pt; border-bottom: .5pt solid #d6d3d1; padding: 9pt 0; break-inside: avoid; }
        .reference { font: 6.5pt Arial,sans-serif; letter-spacing:.08em; text-transform:uppercase; color:#78716c; margin:0 0 5pt; }
        .words { direction:rtl; display:flex; flex-wrap:wrap; align-items:flex-start; gap:5pt 2pt; }
        .word { display:inline-flex; flex-direction:column; align-items:center; break-inside:avoid; white-space:nowrap; padding:0 2pt; }
        .heb { font-family:'Noto Serif Hebrew','Times New Roman',serif; font-size:15pt; line-height:1.1; }
        .gloss { direction:ltr; font:6.5pt Arial,sans-serif; margin-top:2pt; color:#44403c; }
        .english { border-left:.5pt solid #d6d3d1; padding-left:12pt; line-height:1.45; }
        .actions { margin: 0 auto 16px; max-width: 8.5in; padding: 10px; font-family:Arial,sans-serif; }
        @media print { .actions { display:none; } }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
<header><div><div class="hebrew-title">{{ $book->hebrew_name }}</div><h1>{{ $book->name }} {{ $chapter->number }}</h1></div><small>Hebrew–English Interlinear Covenant of Christ</small></header>
@foreach ($chapter->passages as $passage)
    <section class="passage">
        <div><p class="reference">{{ $passage->reference }}</p><div class="words" lang="he" dir="rtl">
            @foreach ($passage->hebrewWords as $word)<span class="word"><span class="heb">{{ $word->hebrew }}</span><span class="gloss">{{ $word->english_gloss ?: 'unresolved' }}</span></span>@endforeach
        </div></div>
        <div class="english">{{ $passage->english_text }}</div>
    </section>
@endforeach
</body>
</html>
