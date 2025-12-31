<?php

namespace App\Twig;

use Highlight\Highlighter;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension
{
    private MarkdownConverter $converter;
    private Highlighter $highlighter;

    public function __construct()
    {
        // Configure CommonMark with GFM (GitHub Flavored Markdown) support
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $this->converter = new MarkdownConverter($environment);

        // Configure syntax highlighter
        $this->highlighter = new Highlighter();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [$this, 'renderMarkdown'], ['is_safe' => ['html']]),
            new TwigFilter('markdown_to_html', [$this, 'renderMarkdown'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Convert Markdown to HTML with syntax highlighting
     */
    public function renderMarkdown(string $markdown): string
    {
        // Convert markdown to HTML
        $html = $this->converter->convert($markdown)->getContent();

        // Apply syntax highlighting to code blocks
        $html = $this->highlightCodeBlocks($html);

        return $html;
    }

    /**
     * Find and highlight code blocks in HTML
     */
    private function highlightCodeBlocks(string $html): string
    {
        // Match code blocks: <pre><code class="language-xxx">...</code></pre>
        return preg_replace_callback(
            '/<pre><code(?:\s+class="language-([^"]+)")?>(.*?)<\/code><\/pre>/s',
            function ($matches) {
                $language = $matches[1] ?? 'plaintext';
                $code = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);

                try {
                    // Map language aliases
                    $language = $this->mapLanguageAlias($language);

                    // Highlight the code
                    $highlighted = $this->highlighter->highlight($language, $code);

                    // Return highlighted code with language label
                    return sprintf(
                        '<div class="code-block"><div class="code-block-header"><span class="code-block-lang">%s</span></div><pre class="hljs"><code class="language-%s">%s</code></pre></div>',
                        htmlspecialchars($language),
                        htmlspecialchars($language),
                        $highlighted->value
                    );
                } catch (\Exception $e) {
                    // Fallback: return original code without highlighting
                    return sprintf(
                        '<div class="code-block"><pre><code class="language-%s">%s</code></pre></div>',
                        htmlspecialchars($language),
                        htmlspecialchars($code)
                    );
                }
            },
            $html
        );
    }

    /**
     * Map common language aliases to highlight.php language names
     */
    private function mapLanguageAlias(string $language): string
    {
        $aliases = [
            'yml' => 'yaml',
            'js' => 'javascript',
            'ts' => 'typescript',
            'sh' => 'bash',
            'shell' => 'bash',
            'dockerfile' => 'docker',
            'tf' => 'terraform',
            'hcl' => 'terraform',
        ];

        return $aliases[strtolower($language)] ?? strtolower($language);
    }
}
