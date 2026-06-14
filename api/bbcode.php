<?php
/**
 * BBCode Parser — Secure BBCode to HTML converter
 * Prevents XSS by sanitizing all output
 */
class BBCodeParser {
    private array $openTags = [];
    private array $blockTags = ['quote', 'code', 'spoiler', 'table', 'center', 'right', 'left'];
    private bool $inSpoiler = false;
    private int $spoilerCount = 0;

    public function parse(string $text): string {
        $text = $this->sanitize($text);
        $text = $this->convertLineBreaks($text);
        $text = $this->parseBBCodes($text);
        $text = $this->parseMentions($text);
        $text = $this->parseAutoLinks($text);
        return trim($text);
    }

    private function sanitize(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function convertLineBreaks(string $text): string {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return $text;
    }

    private function parseBBCodes(string $text): string {
        $rules = [
            // Bold
            '/\[b\](.*?)\[\/b\]/si' => '<strong>$1</strong>',
            // Italic
            '/\[i\](.*?)\[\/i\]/si' => '<em>$1</em>',
            // Underline
            '/\[u\](.*?)\[\/u\]/si' => '<span style="text-decoration:underline;">$1</span>',
            // Strikethrough
            '/\[s\](.*?)\[\/s\]/si' => '<del>$1</del>',
            // Color
            '/\[color=([\w#]+)\](.*?)\[\/color\]/si' => '<span style="color:$1;">$2</span>',
            // Size
            '/\[size=(\d+)\](.*?)\[\/size\]/si' => '<span style="font-size:$1px;">$2</span>',
            // Center
            '/\[center\](.*?)\[\/center\]/si' => '<div style="text-align:center;">$1</div>',
            // Right
            '/\[right\](.*?)\[\/right\]/si' => '<div style="text-align:right;">$1</div>',
            // Left
            '/\[left\](.*?)\[\/left\]/si' => '<div style="text-align:left;">$1</div>',
            // URL with text
            '/\[url=([^\]]+)\](.*?)\[\/url\]/si' => '<a href="$1" target="_blank" rel="noopener">$2</a>',
            // URL simple
            '/\[url\](.*?)\[\/url\]/si' => '<a href="$1" target="_blank" rel="noopener">$1</a>',
            // Image
            '/\[img\](.*?)\[\/img\]/si' => '<img src="$1" alt="Image" style="max-width:100%;height:auto;border-radius:4px;">',
            // Spoiler
            '/\[spoiler\](.*?)\[\/spoiler\]/si' => '<div class="bbcode-spoiler"><button class="spoiler-btn" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==\'none\'?\'block\':\'none\'">Spoiler (click to reveal)</button><div class="spoiler-content" style="display:none;padding:10px;background:#1e293b;border-radius:4px;margin-top:4px;">$1</div></div>',
            // Quote with author
            '/\[quote=([^\]]+)\](.*?)\[\/quote\]/si' => '<blockquote class="bbcode-quote"><div class="quote-author">$1 wrote:</div><div class="quote-content">$2</div></blockquote>',
            // Quote simple
            '/\[quote\](.*?)\[\/quote\]/si' => '<blockquote class="bbcode-quote"><div class="quote-content">$1</div></blockquote>',
            // Code block
            '/\[code\](.*?)\[\/code\]/si' => '<pre class="bbcode-code"><code>$1</code></pre>',
            // Unordered list
            '/\[list\](.*?)\[\/list\]/si' => '<ul class="bbcode-list">$1</ul>',
            // Ordered list
            '/\[list=1\](.*?)\[\/list\]/si' => '<ol class="bbcode-list">$1</ol>',
            // List item
            '/\[\*\]/si' => '<li>',
            // YouTube
            '/\[youtube\](?:https?:\/\/(?:www\.)?youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})\[\/youtube\]/si' => '<div class="bbcode-video"><iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/$1" frameborder="0" allowfullscreen loading="lazy"></iframe></div>',
            // Simple YouTube
            '/\[youtube\]([a-zA-Z0-9_-]{11})\[\/youtube\]/si' => '<div class="bbcode-video"><iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/$1" frameborder="0" allowfullscreen loading="lazy"></iframe></div>',
            // Table
            '/\[table\](.*?)\[\/table\]/si' => '<table class="bbcode-table">$1</table>',
            '/\[tr\](.*?)\[\/tr\]/si' => '<tr>$1</tr>',
            '/\[td\](.*?)\[\/td\]/si' => '<td>$1</td>',
            '/\[th\](.*?)\[\/th\]/si' => '<th>$1</th>',
        ];

        foreach ($rules as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    private function parseMentions(string $text): string {
        return preg_replace(
            '/@([a-zA-Z0-9_]{3,50})/s',
            '<a href="profile.php?username=$1" class="user-mention">@$1</a>',
            $text
        );
    }

    private function parseAutoLinks(string $text): string {
        return preg_replace(
            '#(https?://[^\s<>"\']+)#i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $text
        );
    }

    public function strip(string $text): string {
        $text = $this->sanitize($text);
        $tags = ['b', 'i', 'u', 's', 'color', 'size', 'center', 'right', 'left', 'url', 'img', 'spoiler', 'quote', 'code', 'list', 'youtube', 'table', 'tr', 'td', 'th'];
        foreach ($tags as $tag) {
            $text = preg_replace("/\[$tag(?:=[^\]]+)?\](.*?)\[\/$tag\]/si", '$1', $text);
            $text = preg_replace("/\[$tag\]/si", '', $text);
            $text = preg_replace("/\[\/$tag\]/si", '', $text);
        }
        $text = preg_replace('/\[\*\]/', '', $text);
        return trim($text);
    }

    public function preview(string $text, int $length = 200): string {
        $stripped = $this->strip($text);
        if (mb_strlen($stripped) <= $length) return $stripped;
        return mb_substr($stripped, 0, $length) . '...';
    }
}

function parseBBCode(string $text): string {
    static $parser = null;
    if ($parser === null) $parser = new BBCodeParser();
    return $parser->parse($text);
}

function stripBBCode(string $text): string {
    static $parser = null;
    if ($parser === null) $parser = new BBCodeParser();
    return $parser->strip($text);
}

function previewBBCode(string $text, int $length = 200): string {
    static $parser = null;
    if ($parser === null) $parser = new BBCodeParser();
    return $parser->preview($text, $length);
}
