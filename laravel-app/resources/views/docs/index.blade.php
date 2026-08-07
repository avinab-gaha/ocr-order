<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --accent: #2563eb; }
        body { background: #f8fafc; min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .sidebar { position: sticky; top: 1rem; }
        .sidebar .nav-link { color: #475569; font-size: .9rem; padding: .35rem .75rem; border-radius: .5rem; }
        .sidebar .nav-link:hover { background: #e2e8f0; }
        .sidebar .nav-link.active { background: var(--accent); color: #fff; }
        .card-doc { border: none; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,.06); padding: 2rem; background: #fff; }
        .card-doc h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; }
        .card-doc h2 { font-size: 1.3rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 1px solid #e2e8f0; }
        .card-doc h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.5rem; }
        .card-doc pre { background: #1e293b; color: #e2e8f0; border-radius: .75rem; padding: 1rem; font-size: .85rem; overflow-x: auto; }
        .card-doc code { background: #f1f5f9; padding: .15rem .4rem; border-radius: .25rem; font-size: .85rem; }
        .card-doc pre code { background: transparent; padding: 0; }
        .card-doc table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .card-doc th, .card-doc td { border: 1px solid #e2e8f0; padding: .5rem .75rem; text-align: left; font-size: .9rem; }
        .card-doc th { background: #f8fafc; font-weight: 600; }
        .card-doc img { max-width: 100%; border-radius: .5rem; }
        .card-doc blockquote { border-left: 4px solid var(--accent); padding: .5rem 1rem; margin: 1rem 0; background: #f8fafc; border-radius: .5rem; }
        .card-doc ul, .card-doc ol { padding-left: 1.25rem; }
        .card-doc li { margin-bottom: .25rem; }
        .list-group-item { border: none; padding: .5rem .75rem; border-radius: .5rem !important; color: #475569; }
        .list-group-item:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-2 mb-4">
                <h5 class="fw-bold mb-3" style="color:var(--accent)">Docs</h5>
                <div class="list-group">
                    @foreach($files as $f)
                        <a href="{{ route('docs.show', $f->path) }}"
                           class="list-group-item list-group-item-action small">
                            {{ $f->title }}
                        </a>
                    @endforeach
                    <a href="{{ route('docs.index') }}" class="list-group-item list-group-item-action small fw-bold mt-2">
                        &larr; Back to Index
                    </a>
                </div>
            </div>
            <div class="col-lg-10">
                <div class="card-doc">
                    <h1>Documentation Index</h1>
                    <p class="text-muted mb-4">Select a document from the sidebar to view.</p>
                    <div class="row g-3">
                        @foreach($files as $f)
                            <div class="col-md-6">
                                <a href="{{ route('docs.show', $f->path) }}" class="text-decoration-none">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius:.75rem;">
                                        <div class="card-body">
                                            <h5 class="card-title fw-semibold" style="color:var(--accent)">{{ $f->title }}</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
