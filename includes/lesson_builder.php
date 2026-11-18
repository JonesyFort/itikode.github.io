<?php
declare(strict_types=1);
/**
 * Lesson builder.
 *
 * Teachers fill in structured fields in the editor (assets/builder.js). Each lesson is a subtitle plus
 * parts; a part has a layout (explanation + code practice, card grid, outcomes + checklist, or video +
 * reflection). This file validates that data ("blocks") and compiles it into the designed lesson HTML
 * learners see. Every value is escaped, so teachers never write or paste raw HTML.
 *
 * Block shape:
 *   ['subtitle' => '', 'parts' => [[
 *       'key' => 'a1b2c3', 'emoji' => '📘', 'title' => '', 'concept' => '', 'points' => ['', ''],
 *       'analogy' => '', 'example' => '',
 *       'sandbox' => ['on' => true, 'task' => '', 'starter' => '', 'solution' => '', 'hint' => '',
 *                     'expected' => [['t' => 'line'|'has', 'v' => '']]],
 *   ]]]
 */

const LB_ICONS = [
    'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'quote'       => '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>',
    'bulb'        => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
    'rotate'      => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'terminal'    => '<path d="m4 17 6-6-6-6"/><path d="M12 19h8"/>',
    'play'        => '<path d="M6 3l14 9-14 9z"/>',
    'eye'         => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    'checkcircle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    'playcircle'  => '<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4z"/>',
    'wrench'      => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'check'       => '<path d="M20 6 9 17l-5-5"/>',
    'chev'        => '<path d="m9 18 6-6-6-6"/>',
    'star'        => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
    'sparkles'    => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>',
];

function lbIcon(string $name, int $size = 16, string $cls = ''): string
{
    return '<svg' . ($cls !== '' ? ' class="' . $cls . '"' : '') . ' xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
         . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . (LB_ICONS[$name] ?? '') . '</svg>';
}

/* ── text helpers ──────────────────────────────────────────────────────────── */

/** Escape text; **bold**, *italic* and `code` become tags. */
function lbRich(string $text): string
{
    $parts = preg_split('/(\*\*[^*]+\*\*|`[^`]+`|\*[^*]+\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $len = strlen($p);
        if ($len > 4 && substr($p, 0, 2) === '**' && substr($p, -2) === '**')      $out .= '<strong>' . e(substr($p, 2, -2)) . '</strong>';
        elseif ($len > 2 && $p[0] === '`' && substr($p, -1) === '`')                $out .= '<code>' . e(substr($p, 1, -1)) . '</code>';
        elseif ($len > 2 && $p[0] === '*' && substr($p, -1) === '*')                $out .= '<em>' . e(substr($p, 1, -1)) . '</em>';
        else                                                                        $out .= e($p);
    }
    return $out;
}

/** Blank-line separated paragraphs. */
function lbParas(string $text): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $out .= '<p class="lx-prose">' . str_replace("\n", '<br>', lbRich($para)) . '</p>';
    }
    return $out;
}

/** Python syntax colouring for the example block. */
function lbHighlight(string $code): string
{
    static $kw = ['class', 'def', 'return', 'if', 'else', 'elif', 'for', 'in', 'pass', 'print', 'self', 'import', 'from', 'and', 'or', 'not', 'True', 'False', 'None', 'while'];
    $re  = '/(#[^\n]*)|("(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\')|(@\w+(?:\.\w+)?)|\b(\d+(?:\.\d+)?)\b|\b([A-Za-z_]\w*)\b/';
    $out = '';
    $last = 0;
    if (preg_match_all($re, $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $hit) {
            $text = $hit[0][0];
            $pos  = $hit[0][1];
            if ($pos > $last) $out .= e(substr($code, $last, $pos - $last));
            $cls  = '';
            $word = $hit[5][0] ?? '';
            if (($hit[1][0] ?? '') !== '')      $cls = 'c';
            elseif (($hit[2][0] ?? '') !== '')  $cls = 's';
            elseif (($hit[3][0] ?? '') !== '')  $cls = 'd';
            elseif (($hit[4][0] ?? '') !== '')  $cls = 'n';
            elseif ($word !== '' && in_array($word, $kw, true)) $cls = ($word === 'self') ? 'sf' : 'k';
            $out .= $cls !== '' ? '<span class="' . $cls . '">' . e($text) . '</span>' : e($text);
            $last = $pos + strlen($text);
        }
    }
    return $out . e(substr($code, $last));
}

/* ── data shape ────────────────────────────────────────────────────────────── */

const LB_ACCENTS = ['#55883B', '#9A6735', '#7B5EA7', '#C0592B', '#2F6F8F', '#B3402A'];
const LB_KINDS   = ['learn', 'cards', 'outcomes', 'video'];

function lbEmptySandbox(): array
{
    return ['on' => false, 'task' => '', 'starter' => '', 'solution' => '', 'hint' => '', 'expected' => []];
}

function lbEmptyRound(): array
{
    return ['on' => false, 'task' => '', 'starter' => '', 'solution' => '', 'hint' => '', 'expected' => [], 'mode' => 'match', 'tiles' => [], 'pairs' => []];
}

function lbEmptyPart(): array
{
    return [
        'key' => '', 'kind' => 'learn', 'emoji' => '📘', 'title' => '', 'accent' => '', 'skip_after' => 5, 'round' => lbEmptyRound(),
        'concept' => '', 'facts' => [], 'facts_mono' => false, 'points' => [], 'analogy' => '', 'example' => '', 'footnote' => '',
        'sandbox' => lbEmptySandbox(),
        'cols' => 3, 'cards' => [], 'tiles' => [],
        'out_tag' => '', 'intro' => '', 'outcomes' => [], 'check_tag' => '', 'check_task' => '', 'checks' => [],
        'video_title' => '', 'video_len' => '', 'video_img' => '', 'quote' => '', 'cite' => '', 'prompt' => '', 'placeholder' => '',
    ];
}

function lbNewKey(): string { return 'p' . bin2hex(random_bytes(3)); }

function lbClip(mixed $v, int $max): string
{
    if (is_array($v)) return '';
    $s = str_replace(["\r\n", "\r"], "\n", (string) $v);
    return mb_substr($s, 0, $max);
}

function lbColor(mixed $v, string $fallback): string
{
    $v = is_string($v) ? $v : '';
    return in_array($v, LB_ACCENTS, true) ? $v : $fallback;
}

/** A list of short strings (blank ones dropped). */
function lbStrings(mixed $v, int $maxItems, int $maxLen): array
{
    $out = [];
    foreach (array_slice(is_array($v) ? $v : [], 0, $maxItems) as $s) {
        $s = trim(lbClip($s, $maxLen));
        if ($s !== '') $out[] = $s;
    }
    return $out;
}

/** A list of small records; $fields maps field name => max length. Rows with every field blank are dropped. */
function lbRows(mixed $v, int $maxItems, array $fields): array
{
    $out = [];
    foreach (array_slice(is_array($v) ? $v : [], 0, $maxItems) as $row) {
        if (!is_array($row)) continue;
        $clean = [];
        $any = false;
        foreach ($fields as $name => $max) {
            $clean[$name] = trim(lbClip($row[$name] ?? '', $max));
            if ($clean[$name] !== '') $any = true;
        }
        if ($any) $out[] = $clean;
    }
    return $out;
}

function lbHttpsUrl(mixed $v): string
{
    $u = trim(lbClip($v, 400));
    if ($u === '' || preg_match('#^https://#i', $u) !== 1 || filter_var($u, FILTER_VALIDATE_URL) === false) return '';
    return $u;
}

/** The answers hidden behind each ___ in a starter, found by lining it up with the solution. null if they do not line up. */
function lbBlankAnswers(string $starter, string $solution): ?array
{
    $segs = explode('___', $starter);
    if (count($segs) < 2) return [];
    $re = '/^' . implode('([\s\S]*?)', array_map(fn($x) => preg_quote($x, '/'), $segs)) . '$/';
    if (preg_match($re, $solution, $m) !== 1) return null;
    return array_map('trim', array_slice($m, 1));
}

/** A code exercise (task, starter, solution, hint, output checks): used by the sandbox and the practice round. */
function lbNormalizeExercise(array $sb): array
{
    $out = [
        'on'       => !empty($sb['on']),
        'task'     => trim(lbClip($sb['task'] ?? '', 600)),
        'starter'  => rtrim(ltrim(lbClip($sb['starter'] ?? '', 4000), "\n")),
        'solution' => rtrim(ltrim(lbClip($sb['solution'] ?? '', 4000), "\n")),
        'hint'     => trim(lbClip($sb['hint'] ?? '', 300)),
        'expected' => [],
    ];
    foreach (array_slice(is_array($sb['expected'] ?? null) ? $sb['expected'] : [], 0, 10) as $e) {
        if (!is_array($e)) continue;
        $v = trim(lbClip($e['v'] ?? '', 200));
        if ($v === '') continue;
        $t = (string) ($e['t'] ?? 'line');
        $out['expected'][] = ['t' => in_array($t, ['has', 'not'], true) ? $t : 'line', 'v' => $v];
    }
    return $out;
}

/** Cleans one part (from the editor or the database) into the canonical shape. */
function lbNormalizePart(array $p): array
{
    $out = lbEmptyPart();
    $key = (string) ($p['key'] ?? '');
    $out['key']   = preg_match('/^[A-Za-z0-9_-]{3,40}$/', $key) === 1 ? $key : lbNewKey();
    $kind         = (string) ($p['kind'] ?? 'learn');
    $out['kind']  = in_array($kind, LB_KINDS, true) ? $kind : 'learn';
    $emoji        = trim(lbClip($p['emoji'] ?? '', 8));
    $out['emoji'] = $emoji !== '' ? $emoji : '📘';
    $out['title'] = trim(lbClip($p['title'] ?? '', 120));
    $out['accent'] = lbColor($p['accent'] ?? '', '');

    // explanation + code practice
    $out['concept']    = trim(lbClip($p['concept'] ?? '', 4000));
    $out['analogy']    = trim(lbClip($p['analogy'] ?? '', 600));
    $out['example']    = rtrim(ltrim(lbClip($p['example'] ?? '', 4000), "\n"));
    $out['footnote']   = trim(lbClip($p['footnote'] ?? '', 300));
    $out['facts_mono'] = !empty($p['facts_mono']);
    $out['facts']      = lbRows($p['facts'] ?? [], 6, ['k' => 40, 'v' => 100]);
    $out['points']     = lbStrings($p['points'] ?? [], 12, 300);

    $out['sandbox'] = lbNormalizeExercise(is_array($p['sandbox'] ?? null) ? $p['sandbox'] : []);

    // card-game practice round (a second step for this part)
    $rd = is_array($p['round'] ?? null) ? $p['round'] : [];
    $out['round'] = lbNormalizeExercise($rd);
    $out['round']['pairs'] = lbRows($rd['pairs'] ?? [], 8, ['clue' => 60, 'answer' => 40]);
    $out['round']['pairs'] = array_values(array_filter($out['round']['pairs'], fn($x) => $x['clue'] !== '' && $x['answer'] !== ''));
    // card style: "match" = the same word on two cards; "clue" = a clue card and its answer card
    $out['round']['tiles'] = array_values(array_unique(lbStrings($rd['tiles'] ?? [], 8, 40)));
    $mode = (string) ($rd['mode'] ?? '');
    if (!in_array($mode, ['match', 'clue'], true)) $mode = (!$out['round']['tiles'] && $out['round']['pairs']) ? 'clue' : 'match';   // older saved rounds used clue pairs
    $out['round']['mode'] = $mode;
    $out['skip_after'] = min(10, max(3, (int) ($p['skip_after'] ?? 5)));

    // card grid
    $out['cols'] = ((int) ($p['cols'] ?? 3)) === 4 ? 4 : 3;
    foreach (array_slice(is_array($p['cards'] ?? null) ? $p['cards'] : [], 0, 8) as $c) {
        if (!is_array($c)) continue;
        $card = [
            'emoji' => trim(lbClip($c['emoji'] ?? '', 8)), 'eyebrow' => trim(lbClip($c['eyebrow'] ?? '', 40)),
            'title' => trim(lbClip($c['title'] ?? '', 80)), 'text' => trim(lbClip($c['text'] ?? '', 400)),
            'color' => lbColor($c['color'] ?? '', '#55883B'), 'tag' => trim(lbClip($c['tag'] ?? '', 40)),
            'meta'  => trim(lbClip($c['meta'] ?? '', 30)), 'note' => trim(lbClip($c['note'] ?? '', 80)),
        ];
        if ($card['title'] === '' && $card['text'] === '') continue;
        $out['cards'][] = $card;
    }
    $out['tiles'] = lbRows($p['tiles'] ?? [], 8, ['emoji' => 8, 'title' => 60, 'sub' => 80]);

    // outcomes + checklist
    $out['out_tag']    = trim(lbClip($p['out_tag'] ?? '', 40));
    $out['intro']      = trim(lbClip($p['intro'] ?? '', 300));
    $out['outcomes']   = lbRows($p['outcomes'] ?? [], 8, ['text' => 300, 'tag' => 30]);
    $out['check_tag']  = trim(lbClip($p['check_tag'] ?? '', 30));
    $out['check_task'] = trim(lbClip($p['check_task'] ?? '', 200));
    $out['checks']     = lbStrings($p['checks'] ?? [], 8, 200);

    // video + reflection
    $out['video_title'] = trim(lbClip($p['video_title'] ?? '', 120));
    $out['video_len']   = trim(lbClip($p['video_len'] ?? '', 30));
    $out['video_img']   = lbHttpsUrl($p['video_img'] ?? '');
    $out['quote']       = trim(lbClip($p['quote'] ?? '', 600));
    $out['cite']        = trim(lbClip($p['cite'] ?? '', 80));
    $out['prompt']      = trim(lbClip($p['prompt'] ?? '', 200));
    $out['placeholder'] = trim(lbClip($p['placeholder'] ?? '', 120));
    return $out;
}

function lbPartIsEmpty(array $p): bool
{
    return $p['title'] === '' && $p['concept'] === '' && !$p['points'] && !$p['facts'] && $p['analogy'] === '' && $p['example'] === ''
        && !$p['sandbox']['on'] && empty($p['round']['on']) && !$p['cards'] && !$p['tiles'] && !$p['outcomes'] && !$p['checks'] && $p['video_title'] === '' && $p['quote'] === '';
}

/** Canonical blocks from stored or submitted JSON (or a blank lesson). Never trusts the input. */
function lbFromJson(?string $json): array
{
    $d = $json ? json_decode($json, true) : null;
    if (!is_array($d)) $d = [];
    $as = is_array($d['aside'] ?? null) ? $d['aside'] : [];
    $parts = [];
    foreach (array_slice(is_array($d['parts'] ?? null) ? $d['parts'] : [], 0, 20) as $p) {
        if (!is_array($p)) continue;
        $part = lbNormalizePart($p);
        if (!lbPartIsEmpty($part)) $parts[] = $part;
    }
    return [
        'subtitle' => trim(lbClip($d['subtitle'] ?? '', 300)),
        'kicker'   => trim(lbClip($d['kicker'] ?? '', 40)),
        'accent'   => lbColor($d['accent'] ?? '', '#55883B'),
        'aside'    => ['title' => trim(lbClip($as['title'] ?? '', 60)), 'rows' => lbRows($as['rows'] ?? [], 6, ['l' => 30, 'v' => 80])],
        'parts'    => $parts,
    ];
}

/** The editor posts its whole state as one JSON field. */
function lbFromPost(array $post): array
{
    return lbFromJson((string) ($post['blocks_json'] ?? ''));
}

/** Problems a teacher should fix before saving; empty array = fine. */
function lbValidate(array $blocks, string $title): array
{
    $errors = [];
    if ($title === '') $errors[] = 'Give the lesson a title.';
    if (!$blocks['parts']) $errors[] = 'Add at least one part to the lesson.';
    foreach ($blocks['parts'] as $i => $p) {
        $n = $i + 1;
        if ($p['title'] === '') $errors[] = "Part $n needs a title.";
        if ($p['kind'] === 'cards') {
            if (!$p['cards']) $errors[] = "Part $n: add at least one card.";
        } elseif ($p['kind'] === 'outcomes') {
            if (!$p['outcomes']) $errors[] = "Part $n: add at least one learning outcome.";
        } elseif ($p['kind'] === 'video') {
            if ($p['video_title'] === '') $errors[] = "Part $n: give the video a title.";
        } else {
            if ($p['concept'] === '' && !$p['points'] && !$p['facts'] && !$p['sandbox']['on']) $errors[] = "Part $n needs an explanation, some key points, or a code sandbox.";
            if ($p['sandbox']['on']) {
                if ($p['sandbox']['task'] === '')    $errors[] = "Part $n: describe the task for the code sandbox.";
                if ($p['sandbox']['starter'] === '') $errors[] = "Part $n: add some starter code for the sandbox.";
            }
            if (!empty($p['round']['on'])) {
                $r = $p['round'];
                $cards = $r['mode'] === 'clue' ? array_column($r['pairs'], 'answer') : $r['tiles'];
                if (count($cards) < 4)                    $errors[] = "Part $n: the card game needs at least 4 " . ($r['mode'] === 'clue' ? 'pairs' : 'card words') . " (8 make a 4 x 4 board).";
                if ($r['task'] === '')                    $errors[] = "Part $n: describe the task for the practice.";
                if ($r['starter'] === '' || !str_contains($r['starter'], '___')) $errors[] = "Part $n: the practice starter code needs blanks written as ___ (three underscores).";
                if ($r['solution'] === '' || str_contains($r['solution'], '___')) $errors[] = "Part $n: add the full solution for the practice (with no blanks left).";
                if (!$r['expected'])                      $errors[] = "Part $n: add at least one output check for the practice.";
                if (count($cards) !== count(array_unique($cards))) $errors[] = "Part $n: every card word must be different.";
                $need = lbBlankAnswers($r['starter'], $r['solution']);
                if ($need !== null) {
                    $missing = array_values(array_diff(array_unique($need), $cards));
                    if ($missing) $errors[] = "Part $n: the cards are missing words the blanks need: " . implode(', ', $missing) . '.';
                }
            }
        }
    }
    return $errors;
}

/**
 * Installs the designed versions of the built-in lessons (Motivation, Introduction, Topics 1-4) and records the version.
 * Overwrites any edits made to those six lessons. Returns how many lessons were updated.
 */
function refreshBuiltInLessons(): int
{
    require_once __DIR__ . '/seed_content.php';
    $db  = getDB();
    $upd = $db->prepare("UPDATE sections SET html = ?, blocks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND type = 'lesson'");
    $n = 0;
    foreach ([2, 3, 4, 6, 8, 10] as $sid) {
        $c = seedSectionContent($sid);
        $upd->execute([(string) ($c['html'] ?? ''), lbSeedJson($sid), $sid]);
        $n += $upd->rowCount();
    }
    settingSet('lessons_seed_version', (string) LESSON_SEED_VERSION);
    return $n;
}

/** Is the database holding an older version of the built-in lessons? */
function lessonsOutdated(): bool
{
    return (int) setting('lessons_seed_version') < LESSON_SEED_VERSION;
}

/* ── seed blocks for the built-in lessons ─────────────────────────────────── */

function lbSeedBlocks(int $sectionId): ?array
{
    static $all = null;
    if ($all === null) {
        $raw = @file_get_contents(__DIR__ . '/seed_blocks.json');
        $all = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return isset($all[$sectionId]) && is_array($all[$sectionId]) ? $all[$sectionId] : null;
}

function lbSeedJson(int $sectionId): ?string
{
    $b = lbSeedBlocks($sectionId);
    return $b === null ? null : json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* ── compiling to HTML (a port of tools/compile.mjs) ───────────────────────── */

function lbStage(string $emoji, string $label, string $title, string $accent): string
{
    return '<div class="lx-stage"><div class="lx-stage-ic"><span aria-hidden="true">' . e($emoji) . '</span><span class="st">' . lbIcon('star', 10) . '</span></div>'
         . '<div style="min-width:0"><small style="color:' . $accent . '">' . e($label) . '</small><h2>' . e($title) . '</h2></div><div class="rule"></div></div>';
}

/** Textarea body: escape and keep a leading blank line (browsers drop the first newline). */
function lbTa(string $s): string
{
    $s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    return (isset($s[0]) && $s[0] === "\n") ? "\n" . $s : $s;
}

function lbLearn(array $p): string
{
    $noex = $p['example'] === '' ? ' noex' : '';
    $h = '<div class="lx-card lx-learn' . $noex . '"><div class="lx-learn-top"><div><span class="lx-tag">Learn</span></div>';
    if ($p['concept'] !== '') $h .= lbParas($p['concept']);
    if ($p['points']) {
        $h .= '<ul class="lx-points">';
        foreach ($p['points'] as $pt) $h .= '<li>' . lbRich($pt) . '</li>';
        $h .= '</ul>';
    }
    if ($p['facts']) {
        $n = count($p['facts']);
        $h .= '<div class="lx-facts' . (($n === 2 || $n === 4) ? ' two' : '') . '">';
        foreach ($p['facts'] as $f) {
            $h .= '<div class="lx-fact"><small>' . e($f['k']) . '</small><b' . ($p['facts_mono'] ? ' class="mono"' : '') . '>' . e($f['v']) . '</b></div>';
        }
        $h .= '</div>';
    }
    if ($p['analogy'] !== '') $h .= '<div class="lx-ana">' . lbIcon('quote', 14) . '<p>' . e($p['analogy']) . '</p></div>';
    if ($p['footnote'] !== '') $h .= '<p class="lx-foot">' . e($p['footnote']) . '</p>';
    $h .= '</div>';
    if ($p['example'] !== '') $h .= '<div class="lx-learn-ex"><small>Example</small><pre class="lx-code">' . lbHighlight($p['example']) . '</pre></div>';
    return $h . '</div>';
}

function lbPlayground(array $p, string $label = 'Try it yourself', int $skip = 5, bool $hideSolution = false): string
{
    $sb  = $p['sandbox'];
    $exp = [];
    foreach ($sb['expected'] as $e) {
        if ($e['t'] === 'has')      $exp[] = ['re' => (string) preg_replace('/[.*+?^${}()|\[\]\\\\\/]/', '\\\\$0', $e['v']), 'flags' => ''];   // same escaping as tools/compile.mjs
        elseif ($e['t'] === 'not')  $exp[] = ['not' => $e['v']];
        else                        $exp[] = $e['v'];
    }
    $expAttr = htmlspecialchars((string) json_encode($exp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    $h  = '<div class="lx-play" data-id="' . e($p['key']) . '" data-title="' . e($p['title']) . '" data-skip="' . $skip . '" data-expect="' . $expAttr . '">';
    $h .= '<div class="lx-play-top"><span class="lx-tag solid">' . e($label) . '</span><span class="lx-solved">' . lbIcon('checkcircle', 13) . ' Solved</span></div>';
    $h .= '<p class="lx-task">' . lbRich($sb['task']) . '</p>';
    $h .= '<div class="lx-ed"><div class="lx-ed-bar"><span>main.py</span><span class="lx-ed-btns">'
        . '<button type="button" class="lx-ed-reset">' . lbIcon('rotate', 11) . ' Reset</button>'
        . '<button type="button" class="lx-run" disabled>' . lbIcon('play', 12) . ' <span>Loading Python…</span></button></span></div>'
        . '<div class="lx-ed-body"><div class="lx-gut" aria-hidden="true"></div>'
        . '<textarea class="lx-src" spellcheck="false" wrap="off" rows="10" aria-label="Python code editor">' . lbTa($sb['starter']) . '</textarea></div></div>';
    $h .= '<div class="lx-outbox"><div class="lx-out-h">' . lbIcon('terminal', 11) . ' Output <em>Ctrl + Enter to run</em></div>'
        . '<pre class="lx-out-pre"><span class="lx-out-empty">Press Run to see what your code prints…</span></pre></div>';
    $h .= '<div class="lx-verdict" hidden></div><div class="lx-play-foot">';
    if ($sb['hint'] !== '') $h .= '<button type="button" class="lx-hintbtn">' . lbIcon('bulb', 13) . ' <span>Need a hint?</span></button>';
    if ($sb['solution'] !== '' && !$hideSolution) $h .= '<button type="button" class="lx-solbtn">' . lbIcon('eye', 13) . ' Show solution</button>';
    $h .= '</div>';
    if ($sb['hint'] !== '') $h .= '<p class="lx-hint" hidden><strong>Hint:</strong> ' . e($sb['hint']) . '</p>';
    if (!$hideSolution) $h .= '<textarea class="lx-sol" hidden>' . lbTa($sb['solution']) . '</textarea>';
    return $h . '</div>';
}

/** The flip-the-cards game for a part's practice round. */
function lbGameCard(array $p): string
{
    $clue  = $p['round']['mode'] === 'clue';
    $pairs = [];
    if ($clue) { foreach ($p['round']['pairs'] as $x) $pairs[] = [$x['clue'], $x['answer']]; }
    else       { foreach ($p['round']['tiles'] as $w) $pairs[] = [$w, $w]; }
    $attr = htmlspecialchars((string) json_encode($pairs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    $help = $clue
        ? 'Flip two cards at a time. A clue and its answer make a pair. Collect the pairs, then type the answers you need into the blanks in the Practice.'
        : 'Flip two cards at a time and find the matching words. Each match is collected below. Then type the words you need into the blanks in the Practice.';
    return '<div class="lx-card lx-gamecard" data-gid="' . e($p['key']) . '-round" data-mode="' . ($clue ? 'clue' : 'match') . '" data-pairs="' . $attr . '">'
         . '<div class="lx-game-head"><span class="lx-tag green">Card game</span><span class="lx-game-status" aria-live="polite">0/' . count($pairs) . ' pairs</span></div>'
         . '<p class="lx-game-help">' . $help . '</p>'
         . '<div class="lx-deck" role="group" aria-label="Memory cards"></div>'
         . '<div class="lx-tray"><small>Collected items <em>(type what you need into the Practice)</em></small><div class="lx-chips"><span class="lx-chips-empty">Nothing yet. Match a pair to collect it.</span></div></div></div>';
}

function lbCards(array $p): string
{
    $cols = $p['cols'] === 4 ? 4 : 3;
    $h = '<div class="lx-g' . $cols . '">';
    foreach ($p['cards'] as $c) {
        $col = $c['color'];
        $h .= '<div class="lx-card flat"><div class="lx-colhead" style="--c:' . $col . '">';
        if ($c['eyebrow'] !== '') $h .= '<div><small>' . e($c['eyebrow']) . '</small><b>' . e($c['title']) . '</b></div><span class="em">' . e($c['emoji']) . '</span>';
        else                      $h .= '<span class="em">' . e($c['emoji']) . '</span><b style="flex:1">' . e($c['title']) . '</b>';
        $h .= '</div>';
        if ($c['tag'] !== '' || $c['meta'] !== '' || $c['note'] !== '') {
            $h .= '<div class="pad" style="display:flex;flex-direction:column;flex:1"><p class="lx-sm" style="flex:1;margin:0">' . e($c['text']) . '</p>';
            if ($c['tag'] !== '' || $c['meta'] !== '') {
                $h .= '<div class="lx-road-meta">' . ($c['tag'] !== '' ? '<span style="color:' . $col . ';background:' . $col . '18">' . e($c['tag']) . '</span>' : '<span></span>')
                    . ($c['meta'] !== '' ? '<span class="tm">' . lbIcon('clock', 11) . ' ' . e($c['meta']) . '</span>' : '') . '</div>';
            }
            if ($c['note'] !== '') $h .= '<div class="lx-road-note">' . e($c['note']) . '</div>';
            $h .= '</div>';
        } else {
            $h .= '<p class="lx-sm pad">' . e($c['text']) . '</p>';
        }
        $h .= '</div>';
    }
    $h .= '</div>';
    if ($p['tiles']) {
        $h .= '<div class="lx-uses">';
        foreach ($p['tiles'] as $t) {
            $h .= '<div class="lx-use"><span class="em">' . e($t['emoji']) . '</span><div><b>' . e($t['title']) . '</b><span>' . e($t['sub']) . '</span></div></div>';
        }
        $h .= '</div>';
    }
    return $h;
}

function lbOutcomes(array $p, string $accent): string
{
    $left = '<div class="lx-card"><div><span class="lx-tag">' . e($p['out_tag'] !== '' ? $p['out_tag'] : 'Learning outcomes') . '</span></div>'
          . ($p['intro'] !== '' ? '<p class="lx-prose">' . lbRich($p['intro']) . '</p>' : '') . '<ol class="lx-ilos">';
    foreach ($p['outcomes'] as $i => $o) {
        $left .= '<li><span class="n">' . ($i + 1) . '</span><p>' . e($o['text']) . '</p>' . ($o['tag'] !== '' ? '<span class="t">' . e($o['tag']) . '</span>' : '') . '</li>';
    }
    $left .= '</ol></div>';
    if (!$p['checks'] && $p['check_task'] === '') return $left;
    $n = count($p['checks']);
    $right = '<div class="lx-try" style="--a:' . $accent . '"><div class="lx-play-top" style="justify-content:space-between"><span class="lx-tag solid">' . lbIcon('wrench', 11) . ' '
           . e($p['check_tag'] !== '' ? $p['check_tag'] : 'Get ready') . '</span>'
           . '<span class="lx-ready" data-total="' . $n . '" style="font-size:11px;font-weight:700;color:#55883B">0/' . $n . ' ready</span></div>'
           . ($p['check_task'] !== '' ? '<p class="lx-task" style="margin-top:12px">' . lbRich($p['check_task']) . '</p>' : '') . '<div class="lx-checks">';
    foreach ($p['checks'] as $c) {
        $right .= '<button type="button" class="lx-chk" aria-pressed="false"><i>' . lbIcon('check', 12) . '</i><span>' . e($c) . '</span></button>';
    }
    return '<div class="lx-g2">' . $left . $right . '</div></div></div>';
}

function lbVideo(array $p): string
{
    $img = $p['video_img'] !== '' ? '<img src="' . e($p['video_img']) . '" alt="" loading="lazy">' : '';
    $h = '<div class="lx-g2"><div class="lx-video" role="img" aria-label="Video placeholder: ' . e($p['video_title']) . '">' . $img
       . '<div class="lx-video-in"><div class="lx-playbtn">' . lbIcon('playcircle', 32) . '</div><p>' . e($p['video_title']) . '</p>'
       . ($p['video_len'] !== '' ? '<small>' . e($p['video_len']) . '</small>' : '') . '</div></div>'
       . '<div class="lx-card"><div><span class="lx-tag">Think about it</span></div>';
    if ($p['quote'] !== '') {
        $h .= '<div class="lx-quotebox">' . lbIcon('quote', 28) . '<p>"' . e($p['quote']) . '"</p>' . ($p['cite'] !== '' ? '<cite>' . e($p['cite']) . '</cite>' : '') . '</div>';
    }
    if ($p['prompt'] !== '') {
        $h .= '<label class="lx-thought-l" for="th-' . e($p['key']) . '">' . lbIcon('bulb', 13) . ' ' . e($p['prompt']) . '</label>'
            . '<textarea class="lx-thought" id="th-' . e($p['key']) . '" placeholder="' . e($p['placeholder']) . '"></textarea>';
    }
    return $h . '</div></div>';
}

function lbAside(array $b, int $plays): string
{
    $rows = [];
    foreach ((array) ($b['aside']['rows'] ?? []) as $r) {
        if ($r['l'] !== '' || $r['v'] !== '') $rows[] = $r;
    }
    $atitle = (string) ($b['aside']['title'] ?? '');
    if ($rows || $atitle !== '') {
        $meta = false;
        foreach ($rows as $r) { if ($r['l'] !== '') $meta = true; }
        if ($meta) {
            $h = '<div class="lx-hero-aside"><div class="lx-meta">';
            foreach ($rows as $r) $h .= '<div><em>' . e($r['l']) . '</em><b>' . e($r['v']) . '</b></div>';
            return $h . '</div></div>';
        }
        $h = '<div class="lx-hero-aside"><div class="lx-pg-row"><span>' . e($atitle) . '</span></div><ul class="lx-mini">';
        foreach ($rows as $r) $h .= '<li>' . e($r['v']) . '</li>';
        return $h . '</ul></div>';
    }
    if ($plays > 0) {
        return '<div class="lx-hero-aside"><div class="lx-pg-row"><span>Code practice</span><span class="lx-pg-count">0/' . $plays . '</span></div>'
             . '<div class="lx-pg-bar"><i></i></div><div class="lx-pg-stars">'
             . str_repeat('<span class="lx-star">' . lbIcon('star', 16) . '</span>', $plays) . '</div></div>';
    }
    return '';
}

function lbCompile(array $blocks, int $sectionId, string $title, string $duration): string
{
    $parts  = $blocks['parts'] ?? [];
    $accent = lbColor($blocks['accent'] ?? '', '#55883B');
    $plays  = 0;
    foreach ($parts as $p) {
        if ($p['kind'] !== 'learn') continue;
        if ($p['sandbox']['on']) $plays++;
        if (!empty($p['round']['on'])) $plays++;
    }
    $dur    = $duration !== '' ? $duration : '~15 mins';
    $kicker = (string) ($blocks['kicker'] ?? '');
    $kick   = lbIcon('clock', 12) . ' ' . e($kicker !== '' ? $kicker : 'Lesson') . ' · ' . e($dur)
            . ($plays > 0 ? ' · ' . $plays . ' practice' . ($plays === 1 ? '' : 's') : '');

    $paged = $plays >= 2;   // two or more code sandboxes: show one part at a time, like turning a page
    $h  = '<div class="lx-topic" data-topic="s' . $sectionId . '"' . ($paged ? ' data-paged="1"' : '') . '>';
    $h .= '<div class="lx-hero"' . ($accent !== '#55883B' ? ' style="--tint:' . $accent . '"' : '') . '><div class="lx-hero-in"><div class="lx-hero-txt"><div class="lx-kick">' . $kick . '</div><h1>' . e($title) . '</h1>'
        . (($blocks['subtitle'] ?? '') !== '' ? '<p>' . e((string) $blocks['subtitle']) . '</p>' : '') . '</div>' . lbAside($blocks, $plays) . '</div></div>';

    $count = count($parts);
    if ($count > 1) {
        $h .= '<nav class="lx-steps" aria-label="Lesson parts">';
        foreach ($parts as $i => $p) {
            $h .= '<a href="#' . e($p['key']) . '" data-step="' . e($p['key']) . '"><span>' . e($p['emoji']) . '</span> ' . ($i + 1) . '. ' . e($p['title']) . '</a>';
        }
        $h .= '</nav>';
    }

    $h .= '<div class="lx-sections" style="margin-top:24px">';
    foreach ($parts as $i => $p) {
        $pa = $p['accent'] !== '' ? $p['accent'] : $accent;
        $h .= '<section id="' . e($p['key']) . '" class="lx-chal" data-part="' . $i . '">' . lbStage($p['emoji'], 'Part ' . ($i + 1) . ' of ' . $count, $p['title'], $accent);
        if ($p['kind'] === 'cards')         $h .= lbCards($p);
        elseif ($p['kind'] === 'outcomes')  $h .= lbOutcomes($p, $pa);
        elseif ($p['kind'] === 'video')     $h .= lbVideo($p);
        else {
            $learn = $p['sandbox']['on']
                ? '<div class="lx-g2">' . lbLearn($p) . '<div class="lx-try" style="--a:' . $pa . '">' . lbPlayground($p, 'Try it yourself', (int) $p['skip_after']) . '</div></div>'
                : lbLearn($p);
            if (!empty($p['round']['on'])) {
                $rp = ['key' => $p['key'] . '-round', 'title' => $p['title'] . ' · Practice', 'sandbox' => $p['round']];
                $h .= '<div class="lx-step" data-substep="learn">' . $learn . '</div>'
                    . '<div class="lx-step" data-substep="round" hidden><div class="lx-g2">' . lbGameCard($p)
                    . '<div class="lx-try" style="--a:' . $pa . '">' . lbPlayground($rp, 'Practice', (int) $p['skip_after'], true) . '</div></div></div>';
            } else {
                $h .= $learn;
            }
        }
        $h .= '</section>';
    }
    $h .= '</div>';

    if ($paged) {
        $h .= '<div class="lx-pager"><button type="button" class="lx-btn ghost lx-pg-prev" hidden>' . lbIcon('chev', 15, 'flip') . ' Previous</button>'
            . '<span class="lx-pg-hint"></span><button type="button" class="lx-pg-skip">Skip for now</button>'
            . '<button type="button" class="lx-btn lx-pg-next" disabled><span class="lbl">Next practice</span> ' . lbIcon('chev', 15) . '</button></div>';
    }
    if ($plays > 0) {
        $h .= '<div class="lx-wrapup" data-total="' . $plays . '"><div class="ic">' . lbIcon('sparkles', 22) . '</div>'
            . '<div><b>' . $plays . ' practice' . ($plays === 1 ? '' : 's') . ' left</b><p>Work through each part. You can still continue any time.</p></div></div>';
    }
    return $h . '</div>';
}
