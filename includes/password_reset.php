<?php
/**
 * RG PMS - Password reset OTP helpers
 */

function generate_otp(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function create_password_reset(int $userId): array|false {
    $pdo = db();

    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
        ->execute([$userId]);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) return false;

    $otp     = generate_otp();
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 15 * 60);

    $ins = $pdo->prepare(
        'INSERT INTO password_resets (user_id, otp_code, token, expires_at) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$userId, $otp, $token, $expires]);

    return ['otp' => $otp, 'token' => $token];
}

function find_pending_reset(string $token): ?array {
    if ($token === '') return null;
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets
         WHERE token = ? AND used = 0
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (strtotime($row['expires_at']) < time()) return null;
    return $row;
}

function verify_password_reset(string $token, string $otp, bool $markUsed = true): int|false {
    $otp = preg_replace('/\D/', '', $otp);
    if ($token === '' || strlen($otp) !== 6) return false;

    $row = find_pending_reset($token);
    if (!$row) return false;
    if (!hash_equals((string)$row['otp_code'], (string)$otp)) return false;

    if ($markUsed) {
        db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
            ->execute([$row['id']]);
    }
    return (int)$row['user_id'];
}

function has_pending_reset(int $userId): bool {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT 1 FROM password_resets
         WHERE user_id = ? AND used = 0 AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

/* ============================================================
 *  SESSION HELPERS - bridge between step 1 (verify OTP) and
 *  step 2 (set new password)
 * ============================================================ */

const PWD_RESET_SESSION_TTL = 600; // 10 minutes

function pwd_reset_mark_verified(int $userId): void {
    $_SESSION['pwd_reset_user_id']     = $userId;
    $_SESSION['pwd_reset_verified_at'] = time();
}

function pwd_reset_current_user(): ?int {
    $userId     = $_SESSION['pwd_reset_user_id']     ?? null;
    $verifiedAt = $_SESSION['pwd_reset_verified_at'] ?? 0;
    if (!$userId || (time() - (int)$verifiedAt) > PWD_RESET_SESSION_TTL) {
        pwd_reset_clear_session();
        return null;
    }
    return (int)$userId;
}

function pwd_reset_clear_session(): void {
    unset($_SESSION['pwd_reset_user_id'], $_SESSION['pwd_reset_verified_at']);
}

/**
 * Render an OTP as a captcha-style inline SVG.
 */
function render_captcha_otp(string $otp): string {
    $chars  = str_split($otp);
    $n      = max(1, count($chars));
    $width  = 260;
    $height = 90;

    $r = function ($min, $max) { return random_int($min, $max); };
    $palette = ['#3b82f6', '#1e40af', '#64748b', '#0f172a', '#475569', '#cbd5e1'];

    $svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" ';
    $svg .= 'preserveAspectRatio="xMidYMid meet" ';
    $svg .= 'style="width:100%;max-width:' . $width . 'px;height:auto;display:block;border-radius:12px;" ';
    $svg .= 'role="img" aria-label="Security code">';

    $svg .= '<defs>';
    $svg .=   '<linearGradient id="capBg" x1="0" y1="0" x2="1" y2="1">';
    $svg .=     '<stop offset="0%" stop-color="#eef2f7"/>';
    $svg .=     '<stop offset="100%" stop-color="#dbe3ee"/>';
    $svg .=   '</linearGradient>';
    $svg .=   '<pattern id="capDots" width="6" height="6" patternUnits="userSpaceOnUse">';
    $svg .=     '<circle cx="1" cy="1" r="0.5" fill="#94a3b8" opacity="0.35"/>';
    $svg .=   '</pattern>';
    $svg .= '</defs>';

    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="url(#capBg)"/>';
    $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="url(#capDots)"/>';

    for ($i = 0; $i < 5; $i++) {
        $x1 = $r(-10, $width);
        $y1 = $r(0, $height);
        $cx = $r(0, $width);
        $cy = $r(0, $height);
        $x2 = $r(0, $width + 10);
        $y2 = $r(0, $height);
        $c  = $palette[$r(0, count($palette) - 1)];
        $w  = $r(1, 2);
        $op = $r(30, 60) / 100;
        $svg .= '<path d="M' . $x1 . ' ' . $y1 . ' Q' . $cx . ' ' . $cy . ' ' . $x2 . ' ' . $y2 . '" ';
        $svg .=   'stroke="' . $c . '" stroke-width="' . $w . '" fill="none" opacity="' . $op . '"/>';
    }

    for ($i = 0; $i < 30; $i++) {
        $cx  = $r(0, $width);
        $cy  = $r(0, $height);
        $rad = $r(5, 15) / 10;
        $c   = $palette[$r(0, count($palette) - 1)];
        $op  = $r(20, 55) / 100;
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rad . '" fill="' . $c . '" opacity="' . $op . '"/>';
    }

    $spacing    = $width / ($n + 1);
    $darkColors = ['#0f172a', '#1e293b', '#1e40af', '#0c1526'];
    foreach ($chars as $i => $ch) {
        $x    = $spacing * ($i + 1);
        $y    = $height / 2 + $r(-6, 6);
        $rot  = $r(-18, 18);
        $size = $r(38, 48);
        $c    = $darkColors[$r(0, 3)];

        $svg .= '<text x="' . ($x + 1) . '" y="' . ($y + 1) . '" font-family="Georgia, serif" '
              .   'font-size="' . $size . '" font-weight="700" fill="#ffffff" opacity="0.5" '
              .   'text-anchor="middle" dominant-baseline="middle" '
              .   'transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')">'
              .   htmlspecialchars($ch, ENT_XML1) . '</text>';

        $svg .= '<text x="' . $x . '" y="' . $y . '" font-family="Georgia, serif" '
              .   'font-size="' . $size . '" font-weight="700" fill="' . $c . '" '
              .   'text-anchor="middle" dominant-baseline="middle" '
              .   'transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')">'
              .   htmlspecialchars($ch, ENT_XML1) . '</text>';
    }

    for ($i = 0; $i < 3; $i++) {
        $x1 = $r(0, $width);
        $y1 = $r(0, $height);
        $x2 = $r(0, $width);
        $y2 = $r(0, $height);
        $cx = $r(0, $width);
        $cy = $r(0, $height);
        $c  = $palette[$r(0, count($palette) - 1)];
        $op = $r(40, 70) / 100;
        $svg .= '<path d="M' . $x1 . ' ' . $y1 . ' Q' . $cx . ' ' . $cy . ' ' . $x2 . ' ' . $y2 . '" '
              .   'stroke="' . $c . '" stroke-width="1.5" fill="none" opacity="' . $op . '"/>';
    }

    $svg .= '<circle cx="10" cy="10" r="3" fill="#3b82f6" opacity="0.5"/>';
    $svg .= '<circle cx="' . ($width - 10) . '" cy="' . ($height - 10) . '" r="3" fill="#3b82f6" opacity="0.5"/>';
    $svg .= '<polygon points="' . ($width - 22) . ',10 ' . ($width - 14) . ',22 ' . ($width - 30) . ',22" fill="#94a3b8" opacity="0.5"/>';
    $svg .= '<rect x="8" y="' . ($height - 20) . '" width="10" height="10" rx="2" fill="#94a3b8" opacity="0.5" '
          . 'transform="rotate(15 13 ' . ($height - 15) . ')"/>';

    $svg .= '</svg>';
    return $svg;
}