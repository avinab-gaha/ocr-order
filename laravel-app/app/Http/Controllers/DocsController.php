<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

class DocsController extends Controller
{
    public function index()
    {
        $files = collect(glob(resource_path('docs/*.md')))
            ->map(fn ($path) => (object) [
                'filename' => pathinfo($path, PATHINFO_FILENAME),
                'title' => $this->titleFromFilename(pathinfo($path, PATHINFO_FILENAME)),
                'path' => strtolower(pathinfo($path, PATHINFO_FILENAME)),
            ])
            ->sortBy('title');

        return view('docs.index', compact('files'));
    }

    public function show(string $file)
    {
        $path = resource_path("docs/{$file}.md");

        if (!file_exists($path)) {
            abort(404);
        }

        $content = file_get_contents($path);
        $html = Str::markdown($content);
        $title = $this->titleFromFilename($file);

        $files = collect(glob(resource_path('docs/*.md')))
                ->map(fn ($p) => (object) [
                    'filename' => pathinfo($p, PATHINFO_FILENAME),
                    'title' => $this->titleFromFilename(pathinfo($p, PATHINFO_FILENAME)),
                    'path' => strtolower(pathinfo($p, PATHINFO_FILENAME)),
                ])
                ->sortBy('title');

        return view('docs.show', compact('html', 'title', 'files', 'file'));
    }

    protected function titleFromFilename(string $filename): string
    {
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $filename);
    }
}
