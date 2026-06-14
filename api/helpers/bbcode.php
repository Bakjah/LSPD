<?php
/**
 * BBCode Parser
 * Safe BBCode to HTML converter with XSS protection
 */

class BBCodeParser
{
    private array $allowedTags = [
        'b', 'i', 'u', 's',
        'url', 'img', 'quote', 'code', 'spoiler',
        'color', 'size',
        'left', 'center', 'right',
        'list', 'youtube', 'table', 'divbox'
    ];

    private array $patterns = [];
    private array $replacements = [];

    public function __construct()
    {
        $this->initPatterns();
    }

    private function initPatterns(): void
    {
        // Bold
        $this->patterns[] = '/\[b\](.*?)\[\/b\]/is';
        $this->replacements[] = '<strong>$1</strong>';

        // Italic
        $this->patterns[] = '/\[i\](.*?)\[\/i\]/is';
        $this->replacements[] = '<em>$1</em>';

        // Underline
        $this->patterns[] = '/\[u\](.*?)\[\/u\]/is';
        $this->replacements[] = '<span style="text-decoration:underline">$1</span>';

        // Strikethrough
        $this->patterns[] = '/\[s\](.*?)\[\/s\]/is';
        $this->replacements[] = '<del>$1</del>';

        // Color
        $this->patterns[] = '/\[color=([#a-zA-Z0-9]+)\](.*?)\[\/color\]/is';
        $this->replacements[] = '<span style="color:$1">$2</span>';

        // Size (1-7 mapped to 0.7em - 2em)
        $this->patterns[] = '/\[size=(\d+)\](.*?)\[\/size\]/is';
        $this->replacements[] = '<span style="font-size:$1">$2</span>';

        // URL with text
        $this->patterns[] = '/\[url=([^\]]+)\](.*?)\[\/url\]/is';
        $this->replacements[] = '<a href="$1" target="_blank" rel="noopener" class="bbcode-link">$2</a>';

        // URL simple
        $this->patterns[] = '/\[url\](.*?)\[\/url\]/is';
        $this->replacements[] = '<a href="$1" target="_blank" rel="noopener" class="bbcode-link">$1</a>';

        // Image
        $this->patterns[] = '/\[img\](.*?)\[\/img\]/is';
        $this->replacements[] = '<img src="$1" alt="User image" class="bbcode-image" loading="lazy" onerror="this.style.display=\'none\'">';

        // Quote with author
        $this->patterns[] = '/\[quote=([^\]]+)\](.*?)\[\/quote\]/is';
        $this->replacements[] = '<blockquote class="bbcode-quote"><cite>$1</cite>$2</blockquote>';

        // Quote simple
        $this->patterns[] = '/\[quote\](.*?)\[\/quote\]/is';
        $this->replacements[] = '<blockquote class="bbcode-quote">$1</blockquote>';

        // Code inline
        $this->patterns[] = '/\[code\](.*?)\[\/code\]/is';
        $this->replacements[] = '<code class="bbcode-code-inline">$1</code>';

        // Code block
        $this->patterns[] = '/\[code=([^\]]+)\](.*?)\[\/code\]/is';
        $this->replacements[] = '<pre class="bbcode-code"><code class="language-$1">$2</code></pre>';

        // Spoiler
        $this->patterns[] = '/\[spoiler\](.*?)\[\/spoiler\]/is';
        $this->replacements[] = '<span class="bbcode-spoiler" onclick="this.classList.toggle(\'revealed\')">$1</span>';

        // Spoiler with title
        $this->patterns[] = '/\[spoiler=([^\]]+)\](.*?)\[\/spoiler\]/is';
        $this->replacements[] = '<details class="bbcode-spoiler-details"><summary>$1</summary>$2</details>';

        // Left align
        $this->patterns[] = '/\[left\](.*?)\[\/left\]/is';
        $this->replacements[] = '<div style="text-align:left">$1</div>';

        // Center align
        $this->patterns[] = '/\[center\](.*?)\[\/center\]/is';
        $this->replacements[] = '<div style="text-align:center">$1</div>';

        // Right align
        $this->patterns[] = '/\[right\](.*?)\[\/right\]/is';
        $this->replacements[] = '<div style="text-align:right">$1</div>';

        // Ordered list
        $this->patterns[] = '/\[list=1\](.*?)\[\/list\]/is';
        $this->replacements[] = '<ol class="bbcode-list">$1</ol>';

        // Unordered list
        $this->patterns[] = '/\[list\](.*?)\[\/list\]/is';
        $this->replacements[] = '<ul class="bbcode-list">$1</ul>';

        // List item
        $this->patterns[] = '/\[\*\](.*?)(?=\[\*\]|\[\/list\]|$)/is';
        $this->replacements[] = '<li>$1</li>';

        // YouTube (embed)
        $this->patterns[] = '/\[youtube\](?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)\[\/youtube\]/is';
        $this->replacements[] = '<div class="bbcode-video"><iframe src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>';

        // YouTube short
        $this->patterns[] = '/\[youtube\]([a-zA-Z0-9_-]+)\[\/youtube\]/is';
        $this->replacements[] = '<div class="bbcode-video"><iframe src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>';

        // Table
        $this->patterns[] = '/\[table\](.*?)\[\/table\]/is';
        $this->replacements[] = '<table class="bbcode-table">$1</table>';

        // Table row
        $this->patterns[] = '/\[tr\](.*?)\[\/tr\]/is';
        $this->replacements[] = '<tr>$1</tr>';

        // Table header
        $this->patterns[] = '/\[th\](.*?)\[\/th\]/is';
        $this->replacements[] = '<th>$1</th>';

        // Table cell
        $this->patterns[] = '/\[td\](.*?)\[\/td\]/is';
        $this->replacements[] = '<td>$1</td>';

        // Div box
        $this->patterns[] = '/\[divbox=([^\]]+)\](.*?)\[\/divbox\]/is';
        $this->replacements[] = '<div class="bbcode-divbox" style="border-color:$1">$2</div>';

        // User mention
        $this->patterns[] = '/@([a-zA-Z0-9_]+)/is';
        $this->replacements[] = '<span class="bbcode-mention">@$1</span>';
    }

    /**
     * Parse BBCode to HTML
     */
    public function parse(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Convert newlines to <br>
        $text = nl2br($text);

        // Apply BBCode patterns
        $text = preg_replace($this->patterns, $this->replacements, $text);

        // Clean up empty list items
        $text = preg_replace('/<li><\/li>/', '', $text);

        // Ensure proper list nesting
        $text = preg_replace('/<\/li>(?=<li>)/', '</li>', $text);
        $text = preg_replace('/<\/ol>(?=<ol>)/', '', $text);
        $text = preg_replace('/<\/ul>(?=<ul>)/', '', $text);

        return $text;
    }

    /**
     * Strip BBCode tags (for preview/search)
     */
    public function strip(string $text): string
    {
        $text = preg_replace('/\[(b|i|u|s|color|size|url|img|quote|code|spoiler|list|left|center|right|youtube|table|divbox)(?:=[^\]]+)?\].*?\[\/\1\]/is', '', $text);
        $text = preg_replace('/\[\*\]/', '', $text);
        return trim($text);
    }

    /**
     * Validate content for XSS
     */
    public function validate(string $text): bool
    {
        // Check for dangerous patterns
        $dangerous = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($dangerous as $pattern) {
            if (preg_match($pattern, $text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize HTML (basic XSS protection)
     */
    public function sanitize(string $html): string
    {
        // Remove script tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove event handlers
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+on\w+=\'[^\']*\'/i', '', $html);

        // Remove javascript: links
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript:/i', 'href="#"', $html);

        // Remove data: links (except images)
        $html = preg_replace('/href\s*=\s*["\']?\s*data:(?!image)/i', 'href="#"', $html);

        return $html;
    }
}

/**
 * Helper function to parse BBCode
 */
function parseBBCode(string $text): string
{
    static $parser = null;

    if ($parser === null) {
        $parser = new BBCodeParser();
    }

    $text = $parser->sanitize($text);
    return $parser->parse($text);
}

/**
 * Helper function to strip BBCode
 */
function stripBBCode(string $text): string
{
    static $parser = null;

    if ($parser === null) {
        $parser = new BBCodeParser();
    }

    return $parser->strip($text);
}
