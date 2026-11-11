<?php
/** Event H1 typography only. Does not change stored titles, metadata or frozen URLs. */
if (!defined('ABSPATH')) { exit; }
function badaround_event_heading($title) {
    $title = preg_replace('/\s+[—–-]\s+/u', ' | ', (string)$title);
    $minor = array_fill_keys(explode(' ', 'a ad al allo alla ai agli alle all da dal dallo dalla dai dagli dalle dall di del dello della dei degli delle dell in nel nello nella nei negli nelle nell con col coi su sul sullo sulla sui sugli sulle sull per tra fra il lo la i gli le l un uno una un e ed o od ma né ne che se'), true);
    $result = preg_replace_callback('/[\p{L}\p{M}\p{N}]+/u', static function($match) use ($minor) {
        $word = $match[0]; $lower = mb_strtolower($word, 'UTF-8');
        if ($word === 'L') { return $word; } // Preserve established place names such as L’Aquila.
        if (isset($minor[$lower])) { return $lower; }
        // Preserve acronyms, model codes and intentional mixed-case brand names.
        if (preg_match('/\p{N}/u', $word) || (mb_strlen($word, 'UTF-8') > 1 && $word === mb_strtoupper($word, 'UTF-8')) || preg_match('/\p{Ll}.*\p{Lu}/u', $word)) { return $word; }
        return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8');
    }, $title);
    return is_string($result) ? $result : $title;
}

/** Shared decorative witness cue; all meaning remains in the adjacent text. */
function badaround_event_witness_icon(){
    return '<svg class="ba-witness-icon" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><path d="M14.3 4.3a2 2 0 0 1 3.4 0l12 21a2 2 0 0 1-1.7 3H4a2 2 0 0 1-1.7-3Z" fill="#fec600" stroke="#b38a00" stroke-width="1.2" stroke-linejoin="round"/><path d="M16 11v8" fill="none" stroke="#171717" stroke-width="3" stroke-linecap="round"/><circle cx="16" cy="23" r="1.6" fill="#171717"/></svg>';
}
