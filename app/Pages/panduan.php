<?php declare(strict_types=1);

function page_panduan(): void
{
    render_header('Panduan Penggunaan');
    
    $mdFile = APP_ROOT . '/PANDUAN.md';
    if (!is_file($mdFile)) {
        echo '<section class="panel"><p class="empty">File panduan tidak ditemukan.</p></section>';
        render_footer();
        return;
    }
    
    $markdown = file_get_contents($mdFile);
    $html = panduan_md_to_html($markdown);
    
    // Extract TOC from headings
    $toc = panduan_extract_toc($html);
    ?>
    <div class="panduan-layout">
        <!-- TOC Sidebar -->
        <aside class="panduan-toc">
            <div class="panduan-toc-title">
                <i data-lucide="book-open"></i>
                Daftar Isi
            </div>
            <nav class="panduan-toc-nav">
                <?= $toc ?>
            </nav>
        </aside>
        
        <!-- Content -->
        <main class="panduan-content">
            <?= $html ?>
        </main>
    </div>
    <?php
    render_footer();
}

function panduan_extract_toc(string $html): string
{
    $toc = '';
    // Match h2 and h3 headings with ids
    if (preg_match_all('/<h([23])\s+id="([^"]+)"[^>]*>(.*?)<\/h\1>/', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $level = (int)$match[1];
            $id = $match[2];
            $text = strip_tags($match[3]);
            $indent = $level === 3 ? ' style="padding-left:20px"' : '';
            $toc .= '<a href="#' . e($id) . '"' . $indent . '>' . e($text) . '</a>';
        }
    }
    return $toc;
}

function panduan_md_to_html(string $md): string
{
    $lines = explode("\n", $md);
    $html = '';
    $inCodeBlock = false;
    $codeContent = '';
    $codeLang = '';
    $inTable = false;
    $tableRows = [];
    $inList = false;
    $listType = '';
    $inBlockquote = false;
    $blockquoteContent = '';
    
    foreach ($lines as $line) {
        // Code blocks
        if (preg_match('/^```(\w*)/', $line, $m)) {
            if ($inCodeBlock) {
                $html .= '<pre class="panduan-code"><code>' . e(trim($codeContent)) . '</code></pre>';
                $codeContent = '';
                $inCodeBlock = false;
            } else {
                $inCodeBlock = true;
                $codeLang = $m[1];
            }
            continue;
        }
        if ($inCodeBlock) {
            $codeContent .= ($codeContent !== '' ? "\n" : '') . $line;
            continue;
        }
        
        // Tables
        if (preg_match('/^\|(.+)\|$/', $line, $m)) {
            $cells = array_map('trim', explode('|', $m[1]));
            // Skip separator row
            if (preg_match('/^[\s\-:|]+$/', $cells[0])) {
                continue;
            }
            if (!$inTable) {
                $inTable = true;
                $tableRows = [];
            }
            $tableRows[] = $cells;
            continue;
        } elseif ($inTable) {
            $html .= panduan_render_table($tableRows);
            $tableRows = [];
            $inTable = false;
        }
        
        // Blockquotes
        if (preg_match('/^>\s*(.*)/', $line, $m)) {
            if (!$inBlockquote) {
                $inBlockquote = true;
                $blockquoteContent = '';
            }
            $blockquoteContent .= ($blockquoteContent ? ' ' : '') . $m[1];
            continue;
        } elseif ($inBlockquote) {
            $html .= '<blockquote class="panduan-quote">' . panduan_inline($blockquoteContent) . '</blockquote>';
            $blockquoteContent = '';
            $inBlockquote = false;
        }
        
        // Empty line
        if (trim($line) === '') {
            if ($inList) {
                $inList = false;
            }
            $html .= "\n";
            continue;
        }
        
        // Horizontal rule
        if (preg_match('/^---+$/', trim($line))) {
            $html .= '<hr class="panduan-hr">';
            continue;
        }
        
        // Headings
        if (preg_match('/^(#{1,6})\s+(.+)/', $line, $m)) {
            $level = strlen($m[1]);
            $text = panduan_inline($m[2]);
            $slug = panduan_slug($m[2]);
            if ($level <= 2) {
                $html .= '<h' . $level . ' id="' . $slug . '">' . $text . '</h' . $level . '>';
            } else {
                $html .= '<h' . $level . '>' . $text . '</h' . $level . '>';
            }
            continue;
        }
        
        // Unordered list
        if (preg_match('/^[\s]*[-*]\s+(.+)/', $line, $m)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList) $html .= '</' . $listType . '>';
                $inList = true;
                $listType = 'ul';
                $html .= '<ul class="panduan-list">';
            }
            $html .= '<li>' . panduan_inline($m[1]) . '</li>';
            continue;
        }
        
        // Ordered list
        if (preg_match('/^\d+\.\s+(.+)/', $line, $m)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList) $html .= '</' . $listType . '>';
                $inList = true;
                $listType = 'ol';
                $html .= '<ol class="panduan-list">';
            }
            $html .= '<li>' . panduan_inline($m[1]) . '</li>';
            continue;
        }
        
        // Close list if needed
        if ($inList) {
            $html .= '</' . $listType . '>';
            $inList = false;
        }
        
        // Regular paragraph
        $html .= '<p>' . panduan_inline($line) . '</p>';
    }
    
    // Close any open elements
    if ($inTable) $html .= panduan_render_table($tableRows);
    if ($inBlockquote) $html .= '<blockquote class="panduan-quote">' . panduan_inline($blockquoteContent) . '</blockquote>';
    if ($inList) $html .= '</' . $listType . '>';
    if ($inCodeBlock) $html .= '<pre class="panduan-code"><code>' . e(trim($codeContent)) . '</code></pre>';
    
    return $html;
}

function panduan_inline(string $text): string
{
    // Bold + italic
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
    // Bold
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    // Italic
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    // Inline code
    $text = preg_replace('/`([^`]+)`/', '<code class="panduan-inline-code">$1</code>', $text);
    // Links
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
    
    return $text;
}

function panduan_slug(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/[\s]+/', '-', $text);
    return trim($text, '-');
}

function panduan_render_table(array $rows): string
{
    if (empty($rows)) return '';
    $html = '<div class="panduan-table-wrap"><table class="panduan-table">';
    $isFirst = true;
    foreach ($rows as $row) {
        if ($isFirst) {
            $html .= '<thead><tr>';
            foreach ($row as $cell) {
                $html .= '<th>' . panduan_inline($cell) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            $isFirst = false;
        } else {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . panduan_inline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table></div>';
    return $html;
}
