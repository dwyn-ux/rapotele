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
    $toc = panduan_extract_toc($html);
    ?>
    <style>html { scroll-behavior: smooth; }</style>
    
    <!-- Page Header -->
    <div class="panduan-page-header">
        <div class="panduan-page-header-left">
            <div class="panduan-page-header-icon">
                <i data-lucide="book-open"></i>
            </div>
            <div>
                <h1 class="panduan-page-title">Panduan Penggunaan</h1>
                <p class="panduan-page-subtitle">Panduan lengkap E-Rapor KumerBot v2.0 untuk Admin, Guru, dan Siswa</p>
            </div>
        </div>
        <div class="panduan-page-header-badge">
            <i data-lucide="file-text"></i>
            <span>v2.0</span>
        </div>
    </div>
    
    <div class="panduan-layout">
        <!-- TOC Sidebar -->
        <aside class="panduan-toc" id="panduan-toc">
            <div class="panduan-toc-title">
                <i data-lucide="list"></i>
                Daftar Isi
            </div>
            <nav class="panduan-toc-nav">
                <?= $toc ?>
            </nav>
            <div class="panduan-toc-footer">
                <i data-lucide="help-circle"></i>
                <span>Butuh bantuan? Hubungi admin sekolah.</span>
            </div>
        </aside>
        
        <!-- Content -->
        <main class="panduan-content" id="panduan-content">
            <?= $html ?>
        </main>
    </div>
    
    <script>
    (function(){
        /* Highlight active TOC item on scroll */
        var tocLinks = document.querySelectorAll('.panduan-toc-nav a');
        var headings = [];
        tocLinks.forEach(function(link){
            var id = link.getAttribute('href').replace('#','');
            var el = document.getElementById(id);
            if (el) headings.push({el: el, link: link});
        });
        if (!headings.length) return;
        
        var observer = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (entry.isIntersecting) {
                    tocLinks.forEach(function(l){ l.classList.remove('active'); });
                    var match = headings.find(function(h){ return h.el === entry.target; });
                    if (match) match.link.classList.add('active');
                }
            });
        }, { rootMargin: '-80px 0px -60% 0px', threshold: 0 });
        
        headings.forEach(function(h){ observer.observe(h.el); });
    })();
    </script>
    <?php
    render_footer();
}

function panduan_extract_toc(string $html): string
{
    $toc = '';
    $num = 0;
    if (preg_match_all('/<h([23])\s+id="([^"]+)"[^>]*>(.*?)<\/h\1>/', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $level = (int)$match[1];
            $id = $match[2];
            $text = strip_tags($match[3]);
            if ($level === 2) {
                $num++;
            }
            $indent = $level === 3 ? ' panduan-toc-sub' : '';
            $numBadge = $level === 2 ? '<span class="panduan-toc-num">' . $num . '</span>' : '';
            $toc .= '<a href="#' . e($id) . '" class="panduan-toc-link' . $indent . '">' . $numBadge . e($text) . '</a>';
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
                $langBadge = $codeLang ? '<span class="panduan-code-lang">' . e($codeLang) . '</span>' : '';
                $html .= '<div class="panduan-code-wrap">' . $langBadge . '<pre class="panduan-code"><code>' . e(trim($codeContent)) . '</code></pre></div>';
                $codeContent = '';
                $codeLang = '';
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
            $html .= '<div class="panduan-quote"><div class="panduan-quote-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><p>' . panduan_inline($blockquoteContent) . '</p></div>';
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
            if ($level === 1) {
                $html .= '<h1 id="' . $slug . '" class="panduan-h1">' . $text . '</h1>';
            } elseif ($level === 2) {
                $html .= '<h2 id="' . $slug . '" class="panduan-h2"><span class="panduan-h2-anchor">#</span>' . $text . '</h2>';
            } elseif ($level === 3) {
                $html .= '<h3 id="' . $slug . '" class="panduan-h3">' . $text . '</h3>';
            } else {
                $html .= '<h' . $level . ' class="panduan-h' . $level . '">' . $text . '</h' . $level . '>';
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
                $html .= '<ol class="panduan-list panduan-ol">';
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
        $html .= '<p class="panduan-p">' . panduan_inline($line) . '</p>';
    }
    
    // Close any open elements
    if ($inTable) $html .= panduan_render_table($tableRows);
    if ($inBlockquote) $html .= '<div class="panduan-quote"><div class="panduan-quote-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div><p>' . panduan_inline($blockquoteContent) . '</p></div>';
    if ($inList) $html .= '</' . $listType . '>';
    if ($inCodeBlock) {
        $langBadge = $codeLang ? '<span class="panduan-code-lang">' . e($codeLang) . '</span>' : '';
        $html .= '<div class="panduan-code-wrap">' . $langBadge . '<pre class="panduan-code"><code>' . e(trim($codeContent)) . '</code></pre></div>';
    }
    
    return $html;
}

function panduan_inline(string $text): string
{
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code class="panduan-inline-code">$1</code>', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="panduan-link">$1</a>', $text);
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
