<?php

namespace App\Support\Help;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class HelpDocuments
{
    /**
     * @var array<string, string>
     */
    private const ALLOWED = [
        'about' => 'about.md',
    ];

    public static function html(string $slug): string
    {
        if (! array_key_exists($slug, self::ALLOWED)) {
            throw new InvalidArgumentException("Unknown help document: {$slug}");
        }

        $path = base_path('Docs/Help/'.self::ALLOWED[$slug]);

        if (! File::exists($path)) {
            throw new InvalidArgumentException("Help document not found: {$slug}");
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert(File::get($path))->getContent();
    }
}
