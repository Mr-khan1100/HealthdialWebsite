<?php
/**
 * Popup/interstitial manager — data layer.
 *
 * One `popups` table drives every modal popup on the site. Admins manage them on
 * the "Popups" admin page (HealthDial/Popups.php): enable/disable, reorder, edit
 * content/timing, choose audience + pages, and create their own custom popups.
 *
 * Two BUILT-IN popups ship seeded (they have special behaviour the admin can't
 * remove, only configure):
 *   - qr_upsell : the "Unlock your review QR" popup (ties into QR-unlock payment)
 *   - promote   : the "Promote this listing" popup (links to promote.php)
 * Everything else is a CUSTOM popup: free-form image + title + text + button link.
 *
 * audience : 'owner' (listing owner only) | 'all' (everyone) | 'logged_in'
 * pages    : comma list of 'listing_detail','home','all'
 */

if (!function_exists('hd_popups_ensure_schema')) {

    /** Create the popups table and seed the two built-ins (idempotent). */
    function hd_popups_ensure_schema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            popup_key VARCHAR(50) DEFAULT NULL,
            is_builtin TINYINT(1) NOT NULL DEFAULT 0,
            title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(255) DEFAULT NULL,
            body TEXT DEFAULT NULL,
            image_path VARCHAR(500) DEFAULT NULL,
            cta_text VARCHAR(100) DEFAULT NULL,
            cta_url VARCHAR(500) DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            audience ENUM('owner','all','logged_in') NOT NULL DEFAULT 'owner',
            pages VARCHAR(255) NOT NULL DEFAULT 'listing_detail',
            delay_seconds INT NOT NULL DEFAULT 3,
            priority INT NOT NULL DEFAULT 0,
            frequency VARCHAR(20) NOT NULL DEFAULT 'session',
            start_at DATETIME DEFAULT NULL,
            end_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_enabled (enabled),
            INDEX idx_key (popup_key)
        )");

        // Self-heal: add frequency to tables created before this column existed.
        $fcol = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'popups' AND COLUMN_NAME = 'frequency'");
        if (!$fcol || $fcol->num_rows === 0) {
            @$conn->query("ALTER TABLE popups ADD COLUMN frequency VARCHAR(20) NOT NULL DEFAULT 'session'");
        }

        // Seed built-ins once.
        hd_popups_seed_builtin($conn, [
            'popup_key' => 'qr_upsell',
            'title'     => 'Get Your Review QR Code',
            'subtitle'  => 'Collect reviews effortlessly',
            'body'      => 'Share a scannable QR with patients — they scan once and land directly on your review section. No app needed. One-time payment, valid forever.',
            'cta_text'  => 'Unlock My QR Code',
            'audience'  => 'owner',
            'pages'     => 'listing_detail',
            'delay'     => 1,
            'priority'  => 1,
        ]);
        hd_popups_seed_builtin($conn, [
            'popup_key' => 'promote',
            'title'     => 'Get 10x More Patients',
            'subtitle'  => 'Promote this listing',
            'body'      => 'Pin your listing to the top of search results, add a verified badge and reach far more patients.',
            'cta_text'  => 'Promote This Listing',
            'audience'  => 'owner',
            'pages'     => 'listing_detail',
            'delay'     => 10,
            'priority'  => 2,
        ]);
    }

    /** Insert a built-in popup row only if its key isn't present yet. */
    function hd_popups_seed_builtin(mysqli $conn, array $d): void
    {
        $key = $d['popup_key'];
        $chk = $conn->prepare("SELECT id FROM popups WHERE popup_key = ? LIMIT 1");
        if (!$chk) {
            return;
        }
        $chk->bind_param('s', $key);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($exists) {
            return;
        }
        $stmt = $conn->prepare("INSERT INTO popups
            (popup_key, is_builtin, title, subtitle, body, cta_text, enabled, audience, pages, delay_seconds, priority)
            VALUES (?, 1, ?, ?, ?, ?, 1, ?, ?, ?, ?)");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param(
            'sssssssii',
            $d['popup_key'], $d['title'], $d['subtitle'], $d['body'], $d['cta_text'],
            $d['audience'], $d['pages'], $d['delay'], $d['priority']
        );
        $stmt->execute();
        $stmt->close();
    }

    /** All popups, ordered for the admin list. */
    function hd_popups_all(mysqli $conn): array
    {
        hd_popups_ensure_schema($conn);
        $rows = [];
        $res = $conn->query("SELECT * FROM popups ORDER BY is_builtin DESC, priority ASC, id ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        return $rows;
    }

    /** A single built-in popup's config by key, or null. */
    function hd_popup_config(mysqli $conn, string $key): ?array
    {
        hd_popups_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT * FROM popups WHERE popup_key = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Does this popup's audience match the current context? */
    function hd_popup_audience_ok(array $row, array $ctx): bool
    {
        switch ($row['audience'] ?? 'owner') {
            case 'all':
                return true;
            case 'logged_in':
                return !empty($ctx['logged_in']);
            case 'owner':
            default:
                return !empty($ctx['is_owner']);
        }
    }

    /** Is the popup live right now (enabled + within its optional schedule)? */
    function hd_popup_in_window(array $row): bool
    {
        if (empty($row['enabled'])) {
            return false;
        }
        $now = time();
        if (!empty($row['start_at']) && strtotime($row['start_at']) > $now) {
            return false;
        }
        if (!empty($row['end_at']) && strtotime($row['end_at']) < $now) {
            return false;
        }
        return true;
    }

    /** Does the popup target this page? */
    function hd_popup_on_page(array $row, string $page): bool
    {
        $pages = array_map('trim', explode(',', (string) ($row['pages'] ?? '')));
        return in_array('all', $pages, true) || in_array($page, $pages, true);
    }

    /**
     * Enabled CUSTOM popups to render on a page for the given context. Built-ins
     * are rendered inline by the host page (they need page-specific wiring), so
     * they're excluded here.
     */
    function hd_popups_visible_custom(mysqli $conn, string $page, array $ctx): array
    {
        hd_popups_ensure_schema($conn);
        $out = [];
        $res = $conn->query("SELECT * FROM popups WHERE is_builtin = 0 AND enabled = 1 ORDER BY priority ASC, id ASC");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                if (hd_popup_in_window($r) && hd_popup_on_page($r, $page) && hd_popup_audience_ok($r, $ctx)) {
                    $out[] = $r;
                }
            }
        }
        return $out;
    }
}
