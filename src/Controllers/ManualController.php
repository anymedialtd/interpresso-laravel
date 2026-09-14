<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ManualController extends BaseController
{
    public string $manualHtml = '';
    /**
     * @var array<int, array{level:int, id:string, title:string}>
     */
    public array $manualSections = [];

    private function loadManual(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/APPLICATION_MANUAL.md';
        $locale = app()->getLocale();
        // A locale must remain a filename component, never a relative path.
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $locale) === 1) {
            $localizedPath = dirname(__DIR__, 2) . '/docs/APPLICATION_MANUAL.' . $locale . '.md';
            if (File::exists($localizedPath)) {
                $path = $localizedPath;
            }
        }

        if (!File::exists($path)) {
            $this->manualHtml = e(__('interpresso::global.manual.not_found'));
            return;
        }

        $markdown = File::get($path);
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', '', $html, 1) ?? $html;
        $this->manualHtml = $this->addHeadingAnchorsAndSections($html);
    }

    protected function addHeadingAnchorsAndSections(string $html): string
    {
        $sections = [];
        $usedIds = [];

        $updatedHtml = preg_replace_callback('/<h([23])\b[^>]*>(.*?)<\/h\1>/is', function (array $matches) use (&$sections, &$usedIds): string {
            $level = (int) $matches[1];
            $innerHtml = trim($matches[2]);
            // Explicit Markdown heading IDs keep bookmarks and quick links
            // stable when a translated heading has a different title.
            $explicitId = null;
            if (preg_match('/\s+\{#([a-z0-9-]+)\}$/', $innerHtml, $anchor) === 1) {
                $explicitId = $anchor[1];
                $innerHtml = substr($innerHtml, 0, -strlen($anchor[0]));
            }
            $title = trim(preg_replace('/\s+/', ' ', strip_tags($innerHtml)) ?? '');

            if ($title === '') {
                return $matches[0];
            }

            $baseId = $explicitId ?? Str::slug($title);
            if ($baseId === '') {
                $baseId = 'section';
            }

            $id = $baseId;
            $suffix = 2;
            while (isset($usedIds[$id])) {
                $id = $baseId . '-' . $suffix;
                $suffix++;
            }
            $usedIds[$id] = true;

            $sections[] = [
                'level' => $level,
                'id' => $id,
                'title' => $title,
            ];

            return sprintf(
                '<h%d id="%s">%s</h%d>',
                $level,
                e($id),
                $innerHtml,
                $level
            );
        }, $html) ?? $html;

        $this->manualSections = $sections;

        return $updatedHtml;
    }

    public function index(): View
    {
        $this->loadManual();
        return view('interpresso::manual', [
            'manualHtml' => $this->manualHtml,
            'manualSections' => $this->manualSections,
        ]);
    }
}
