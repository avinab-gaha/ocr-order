<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Documentation - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --accent: #2563eb; }
        body { background: #f8fafc; min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .docs-container { max-width: 100%; }
        .sidebar { position: sticky; top: 1rem; }
        .sidebar .nav-link { color: #475569; font-size: .9rem; padding: .35rem .75rem; border-radius: .5rem; }
        .sidebar .nav-link:hover { background: #e2e8f0; }
        .sidebar .nav-link.active { background: var(--accent); color: #fff; }
        .card-doc { border: none; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 2rem; background: #fff; overflow: hidden; }
        .card-doc h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; }
        .card-doc h2 { font-size: 1.3rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 1px solid #e2e8f0; }
        .card-doc h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.5rem; }
        .card-doc h4 { font-size: 1rem; font-weight: 600; margin-top: 1.25rem; }
        .card-doc p { line-height: 1.7; color: #334155; }
        .card-doc pre:not(.mermaid) { background: #1e293b; color: #e2e8f0; border-radius: .75rem; padding: 1rem; font-size: .85rem; overflow-x: auto; }
        .card-doc code { background: #f1f5f9; padding: .15rem .4rem; border-radius: .25rem; font-size: .85rem; color: #dc2626; }
        .card-doc pre:not(.mermaid) code { background: transparent; padding: 0; color: #e2e8f0; }
        .card-doc pre.mermaid { background: transparent; padding: 0; border-radius: .75rem; text-align: center; overflow: hidden; cursor: grab; }
        .card-doc pre.mermaid:active { cursor: grabbing; }
        .card-doc .mermaid svg { max-width: 100%; height: auto; display: block; margin: 0 auto; }
        .card-doc .mermaid svg .label { font-size: 14px !important; }
        .card-doc .mermaid svg .cluster-label text { font-size: 14px !important; }
        .card-doc .mermaid svg .edgeLabel { font-size: 13px !important; }
        .mermaid-wrap { position: relative; margin: 1.5rem 0; border-radius: .75rem; border: 1px solid #e2e8f0; background: #fff; overflow: hidden; }
        .mermaid-wrap .zoom-bar { display: flex; align-items: center; gap: 4px; padding: 6px 10px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .mermaid-wrap .zoom-bar .zoom-label { font-size: .75rem; color: #64748b; margin-right: auto; font-weight: 500; }
        .mermaid-wrap .zoom-bar button { width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; color: #475569; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; }
        .mermaid-wrap .zoom-bar button:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        .mermaid-wrap .zoom-bar .zoom-pct { font-size: .8rem; color: #64748b; min-width: 42px; text-align: center; font-variant-numeric: tabular-nums; }
        .mermaid-wrap .diagram-area { overflow: hidden; cursor: grab; min-height: 100px; }
        .mermaid-wrap .diagram-area:active { cursor: grabbing; }
        .mermaid-wrap .diagram-area svg { display: block; margin: 0 auto; max-width: 100%; }
        .card-doc table { width: 100%; border-collapse: collapse; margin: 1rem 0; display: block; overflow-x: auto; }
        .card-doc th, .card-doc td { border: 1px solid #e2e8f0; padding: .5rem .75rem; text-align: left; font-size: .9rem; }
        .card-doc th { background: #f8fafc; font-weight: 600; }
        .card-doc img { max-width: 100%; border-radius: .5rem; }
        .card-doc blockquote { border-left: 4px solid var(--accent); padding: .5rem 1rem; margin: 1rem 0; background: #f8fafc; border-radius: .5rem; }
        .card-doc ul, .card-doc ol { padding-left: 1.25rem; }
        .card-doc li { margin-bottom: .25rem; line-height: 1.6; }
        .card-doc a { color: var(--accent); }
        .card-doc hr { margin: 2rem 0; border-color: #e2e8f0; }
        .list-group-item { border: none; padding: .5rem .75rem; border-radius: .5rem !important; color: #475569; font-size: .9rem; }
        .list-group-item:hover { background: #e2e8f0; }
        .list-group-item.active { background: var(--accent); color: #fff; border: none; }
    </style>
</head>
<body>
    <div class="container py-4 docs-container">
        <div class="row">
            <div class="col-lg-2 mb-4">
                <h5 class="fw-bold mb-3" style="color:var(--accent)">Docs</h5>
                <div class="list-group">
                    @foreach($files as $f)
                        <a href="{{ route('docs.show', $f->path) }}"
                           class="list-group-item list-group-item-action {{ $f->path === $file ? 'active' : '' }}">
                            {{ $f->title }}
                        </a>
                    @endforeach
                    <a href="{{ route('docs.index') }}" class="list-group-item list-group-item-action small mt-2">
                        &larr; Back to Index
                    </a>
                </div>
            </div>
            <div class="col-lg-10">
                <div class="card-doc">
                    {!! $html !!}
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('code.language-mermaid').forEach(function (el) {
            var pre = document.createElement('pre');
            pre.className = 'mermaid';
            pre.textContent = el.textContent;
            el.parentNode.replaceWith(pre);
        });

        mermaid.initialize({
            startOnLoad: true,
            theme: 'base',
            themeVariables: {
                primaryColor: '#eff6ff',
                primaryBorderColor: '#2563eb',
                lineColor: '#1e293b',
                secondaryColor: '#f8fafc',
                tertiaryColor: '#fff',
                clusterBkg: '#f8fafc',
                clusterBorder: '#cbd5e1',
                nodeBorder: '#2563eb',
                mainBkg: '#fff',
                edgeLabelBackground: '#fff',
                nodeTextColor: '#1e293b',
                edgeLabelBorder: 'transparent',
                fontSize: '16px',
                nodeFontSize: '14px',
                edgeLabelFontSize: '13px'
            }
        });

        function setupZoom(svgEl) {
            var svg = d3.select(svgEl);
            var g = svg.select('g');
            if (g.empty()) return;

            var area = svgEl.closest('.diagram-area');
            var zoom = d3.zoom()
                .scaleExtent([0.3, 10])
                .filter(function (event) {
                    return event.type !== 'wheel';
                })
                .on('zoom', function (event) {
                    g.attr('transform', event.transform);
                    var pct = Math.round(event.transform.k * 100);
                    updatePct(svgEl, pct);
                });

            svg.call(zoom);
            svg.on('dblclick.zoom', null);

            svg.on('click', function (event) {
                if (event.shiftKey) {
                    zoom.scaleBy(svg.transition().duration(250), 0.6);
                } else {
                    zoom.scaleBy(svg.transition().duration(250), 1.5);
                }
            });

            svg.on('dblclick', function () {
                svg.transition().duration(350).call(zoom.transform, d3.zoomIdentity);
            });

            var wrap = svgEl.closest('.mermaid-wrap');
            if (wrap) {
                wrap.querySelector('.zoom-in').addEventListener('click', function (e) {
                    e.stopPropagation();
                    zoom.scaleBy(svg.transition().duration(250), 1.5);
                });
                wrap.querySelector('.zoom-out').addEventListener('click', function (e) {
                    e.stopPropagation();
                    zoom.scaleBy(svg.transition().duration(250), 0.6);
                });
                wrap.querySelector('.zoom-reset').addEventListener('click', function (e) {
                    e.stopPropagation();
                    svg.transition().duration(350).call(zoom.transform, d3.zoomIdentity);
                });
            }

            area.addEventListener('wheel', function (e) { e.preventDefault(); }, { passive: false });

            updatePct(svgEl, 100);
        }

        function updatePct(svgEl, pct) {
            var pctEl = svgEl.closest('.mermaid-wrap').querySelector('.zoom-pct');
            if (pctEl) pctEl.textContent = pct + '%';
        }

        function wrapDiagrams() {
            document.querySelectorAll('pre.mermaid').forEach(function (pre) {
                if (pre.parentNode.classList.contains('diagram-area')) return;
                var area = document.createElement('div');
                area.className = 'diagram-area';
                pre.parentNode.insertBefore(area, pre);
                area.appendChild(pre);

                var wrap = area.parentNode.classList.contains('mermaid-wrap') ? area.parentNode : (function () {
                    var w = document.createElement('div');
                    w.className = 'mermaid-wrap';
                    area.parentNode.insertBefore(w, area);
                    w.appendChild(area);

                    var bar = document.createElement('div');
                    bar.className = 'zoom-bar';
                    bar.innerHTML = '<span class="zoom-label">&#9670; Diagram</span>' +
                        '<button class="zoom-out" title="Zoom Out">&minus;</button>' +
                        '<button class="zoom-reset" title="Fit">&#9678;</button>' +
                        '<button class="zoom-in" title="Zoom In">+</button>' +
                        '<span class="zoom-pct">100%</span>';
                    w.insertBefore(bar, area);
                    return w;
                })();
            });
        }

        var checkRendered = setInterval(function () {
            var svgs = document.querySelectorAll('pre.mermaid svg');
            if (svgs.length > 0) {
                clearInterval(checkRendered);
                wrapDiagrams();
                svgs.forEach(function (svg) { setupZoom(svg); });
            }
        }, 200);
    });
</script>
</body>
</html>
