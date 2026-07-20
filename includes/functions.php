<?php
/**
 * AUREUM HOTEL PLATFORM — Core Helper Functions
 * ------------------------------------------------
 * Reservation validation, pricing engine, loyalty tiers, auth helpers.
 */

require_once __DIR__ . '/../config/database.php';

// ============================================================
// PRICING ENGINE
// ============================================================

/**
 * Calculate the effective nightly rate for a category on a given date,
 * applying any active pricing rules (seasonal, weekend, promo, etc).
 */
function calculateNightlyRate($categoryId, $date, $promoCode = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT base_price FROM room_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    if (!$category) return null;

    $rate = (float) $category['base_price'];
    $dayOfWeek = date('N', strtotime($date)); // 6=Sat, 7=Sun

    // Weekend pricing
    $stmt = $db->prepare("SELECT * FROM pricing_rules WHERE category_id = ? AND rule_type = 'weekend' AND is_active = 1");
    $stmt->execute([$categoryId]);
    if (($dayOfWeek == 6 || $dayOfWeek == 7) && ($rule = $stmt->fetch())) {
        $rate = applyAdjustment($rate, $rule);
    }

    // Seasonal / holiday pricing windows that include this date
    $stmt = $db->prepare("SELECT * FROM pricing_rules
        WHERE category_id = ? AND rule_type IN ('seasonal','holiday')
        AND is_active = 1 AND ? BETWEEN start_date AND end_date");
    $stmt->execute([$categoryId, $date]);
    if ($rule = $stmt->fetch()) {
        $rate = applyAdjustment($rate, $rule);
    }

    // Promo code (early_bird, last_minute, group, corporate, promo)
    if ($promoCode) {
        $stmt = $db->prepare("SELECT * FROM pricing_rules
            WHERE category_id = ? AND promo_code = ? AND is_active = 1
            AND (start_date IS NULL OR ? BETWEEN start_date AND end_date)");
        $stmt->execute([$categoryId, $promoCode, $date]);
        if ($rule = $stmt->fetch()) {
            $rate = applyAdjustment($rate, $rule);
        }
    }

    return round($rate, 2);
}

function applyAdjustment($rate, $rule) {
    switch ($rule['adjustment_type']) {
        case 'fixed_price':
            return (float) $rule['adjustment_value'];
        case 'percent_discount':
            return $rate * (1 - $rule['adjustment_value'] / 100);
        case 'percent_increase':
            return $rate * (1 + $rule['adjustment_value'] / 100);
        case 'fixed_discount':
            return max(0, $rate - $rule['adjustment_value']);
        default:
            return $rate;
    }
}

/**
 * Calculate total stay cost across multiple nights (handles per-night rate changes).
 */
function calculateStayTotal($categoryId, $checkIn, $checkOut, $promoCode = null) {
    $total = 0;
    $nights = 0;
    $current = strtotime($checkIn);
    $end = strtotime($checkOut);

    while ($current < $end) {
        $dateStr = date('Y-m-d', $current);
        $nightRate = calculateNightlyRate($categoryId, $dateStr, $promoCode);
        $total += $nightRate;
        $nights++;
        $current = strtotime('+1 day', $current);
    }

    return ['total' => round($total, 2), 'nights' => $nights, 'avg_rate' => $nights ? round($total / $nights, 2) : 0];
}

// ============================================================
// AVAILABILITY & BOOKING VALIDATION
// ============================================================

/**
 * Check if a category has at least one room available for the requested
 * date range. Returns true/false. This checks overlapping reservations,
 * blackout dates, and physically existing active rooms.
 */
function isCategoryAvailable($categoryId, $checkIn, $checkOut, $excludeReservationId = null) {
    $db = getDB();

    // Blackout dates
    $stmt = $db->prepare("SELECT COUNT(*) c FROM blackout_dates
        WHERE category_id = ? AND NOT (end_date <= ? OR start_date >= ?)");
    $stmt->execute([$categoryId, $checkIn, $checkOut]);
    if ($stmt->fetch()['c'] > 0) {
        return ['available' => false, 'reason' => 'Dates fall within a blackout period.'];
    }

    // Total active rooms in category
    $stmt = $db->prepare("SELECT COUNT(*) c FROM rooms WHERE category_id = ? AND is_active = 1 AND status != 'out_of_order'");
    $stmt->execute([$categoryId]);
    $totalRooms = (int) $stmt->fetch()['c'];
    if ($totalRooms === 0) {
        return ['available' => false, 'reason' => 'No active rooms in this category.'];
    }

    // Overlapping reservations (pending/confirmed/checked_in count as "occupied")
    $sql = "SELECT COUNT(*) c FROM reservations
            WHERE category_id = ?
            AND status IN ('pending','confirmed','checked_in')
            AND NOT (check_out <= ? OR check_in >= ?)";
    $params = [$categoryId, $checkIn, $checkOut];
    if ($excludeReservationId) {
        $sql .= " AND id != ?";
        $params[] = $excludeReservationId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $overlapping = (int) $stmt->fetch()['c'];

    if ($overlapping >= $totalRooms) {
        return ['available' => false, 'reason' => 'No rooms left in this category for the selected dates.'];
    }

    return ['available' => true, 'rooms_left' => $totalRooms - $overlapping];
}

/**
 * Validate a booking request fully (occupancy, min stay, dates) before creation.
 */
function validateBookingRequest($categoryId, $checkIn, $checkOut, $adults, $children) {
    $db = getDB();
    $errors = [];

    $today = date('Y-m-d');
    if ($checkIn < $today) $errors[] = 'Check-in date cannot be in the past.';
    if ($checkOut <= $checkIn) $errors[] = 'Check-out date must be after check-in date.';

    $stmt = $db->prepare("SELECT * FROM room_categories WHERE id = ? AND is_active = 1");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    if (!$category) {
        $errors[] = 'Selected room category does not exist.';
        return ['valid' => false, 'errors' => $errors];
    }

    $totalGuests = (int)$adults + (int)$children;
    if ($totalGuests > $category['max_occupancy']) {
        $errors[] = "This room type allows a maximum of {$category['max_occupancy']} guests.";
    }

    if (empty($errors)) {
        $nights = (strtotime($checkOut) - strtotime($checkIn)) / 86400;
        $stmt = $db->prepare("SELECT MIN(min_stay_nights) m FROM pricing_rules
            WHERE category_id = ? AND is_active = 1
            AND (start_date IS NULL OR ? BETWEEN start_date AND end_date)");
        $stmt->execute([$categoryId, $checkIn]);
        $minStay = $stmt->fetch()['m'];
        if ($minStay && $nights < $minStay) {
            $errors[] = "Minimum stay for these dates is {$minStay} night(s).";
        }

        $availability = isCategoryAvailable($categoryId, $checkIn, $checkOut);
        if (!$availability['available']) {
            $errors[] = $availability['reason'];
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors, 'category' => $category];
}

function generateBookingReference() {
    return 'AUR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . '-' . date('y');
}

// ============================================================
// RESERVATION STATUS WORKFLOW
// ============================================================

const VALID_STATUS_TRANSITIONS = [
    'pending'     => ['confirmed', 'cancelled'],
    'confirmed'   => ['checked_in', 'cancelled', 'no_show'],
    'checked_in'  => ['checked_out'],
    'checked_out' => [],
    'cancelled'   => [],
    'no_show'     => [],
];

function canTransitionStatus($currentStatus, $newStatus) {
    return in_array($newStatus, VALID_STATUS_TRANSITIONS[$currentStatus] ?? []);
}

function updateReservationStatus($reservationId, $newStatus, $staffId = null, $note = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT status FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $current = $stmt->fetch();
    if (!$current) return ['success' => false, 'message' => 'Reservation not found.'];

    if (!canTransitionStatus($current['status'], $newStatus)) {
        return ['success' => false, 'message' => "Cannot move from '{$current['status']}' to '{$newStatus}'."];
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $reservationId]);

        $stmt = $db->prepare("INSERT INTO reservation_status_log (reservation_id, old_status, new_status, changed_by, note) VALUES (?,?,?,?,?)");
        $stmt->execute([$reservationId, $current['status'], $newStatus, $staffId, $note]);

        // Auto-create housekeeping task on checkout
        if ($newStatus === 'checked_out') {
            $stmt = $db->prepare("SELECT room_id FROM reservations WHERE id = ?");
            $stmt->execute([$reservationId]);
            $roomId = $stmt->fetch()['room_id'];
            if ($roomId) {
                $stmt = $db->prepare("UPDATE rooms SET status = 'dirty' WHERE id = ?");
                $stmt->execute([$roomId]);
                $stmt = $db->prepare("INSERT INTO housekeeping_tasks (room_id, task_type, scheduled_for) VALUES (?, 'checkout_clean', CURDATE())");
                $stmt->execute([$roomId]);
            }
        }

        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================
// LOYALTY PROGRAM
// ============================================================

function awardLoyaltyPoints($guestId, $amountSpent, $reservationId = null, $reason = 'Booking') {
    $db = getDB();
    $points = (int) floor($amountSpent / 1000); // 1 point per ₦1,000 spent

    $stmt = $db->prepare("INSERT INTO loyalty_transactions (guest_id, points, reason, reservation_id) VALUES (?,?,?,?)");
    $stmt->execute([$guestId, $points, $reason, $reservationId]);

    $stmt = $db->prepare("UPDATE guests SET loyalty_points = loyalty_points + ? WHERE id = ?");
    $stmt->execute([$points, $guestId]);

    recalculateLoyaltyTier($guestId);
    return $points;
}

function recalculateLoyaltyTier($guestId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT loyalty_points FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $points = (int) $stmt->fetch()['loyalty_points'];

    $tier = 'Silver';
    if ($points >= 5000) $tier = 'VIP';
    elseif ($points >= 2000) $tier = 'Platinum';
    elseif ($points >= 500) $tier = 'Gold';

    $stmt = $db->prepare("UPDATE guests SET loyalty_tier = ? WHERE id = ?");
    $stmt->execute([$tier, $guestId]);
    return $tier;
}

// ============================================================
// AUTH HELPERS
// ============================================================

function isGuestLoggedIn() {
    return isset($_SESSION['guest_id']);
}

function isStaffLoggedIn() {
    return isset($_SESSION['staff_id']);
}

function requireGuestLogin() {
    if (!isGuestLoggedIn()) {
        header('Location: ' . BASE_URL . '/guest/login.php');
        exit;
    }
}

function requireStaffLogin($allowedRoles = []) {
    if (!isStaffLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['staff_role'], $allowedRoles)) {
        header('Location: ' . BASE_URL . '/admin/index.php?error=unauthorized');
        exit;
    }
}

function logActivity($actorType, $actorId, $action, $entityType = null, $entityId = null, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (actor_type, actor_id, action, entity_type, entity_id, ip_address, details) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$actorType, $actorId, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? '', $details]);
}

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Truncate text to a max length without requiring the mbstring extension
 * (not every shared host has it enabled by default).
 */
function truncateText($text, $maxLength = 90, $suffix = '…') {
    $text = trim($text ?? '');
    if (strlen($text) <= $maxLength) return $text;
    $truncated = substr($text, 0, $maxLength);
    // Avoid cutting a word in half
    $lastSpace = strrpos($truncated, ' ');
    if ($lastSpace !== false) $truncated = substr($truncated, 0, $lastSpace);
    return $truncated . $suffix;
}

// ============================================================
// ANALYTICS
// ============================================================

function getOccupancyRate($propertyId, $startDate, $endDate) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) c FROM rooms r JOIN room_categories rc ON r.category_id = rc.id WHERE rc.property_id = ?");
    $stmt->execute([$propertyId]);
    $totalRooms = (int) $stmt->fetch()['c'];
    if ($totalRooms === 0) return 0;

    $totalNightsAvailable = $totalRooms * (max(1, (strtotime($endDate) - strtotime($startDate)) / 86400));

    $stmt = $db->prepare("SELECT SUM(DATEDIFF(LEAST(check_out, ?), GREATEST(check_in, ?))) AS booked
        FROM reservations r JOIN room_categories rc ON r.category_id = rc.id
        WHERE rc.property_id = ? AND status IN ('confirmed','checked_in','checked_out')
        AND NOT (check_out <= ? OR check_in >= ?)");
    $stmt->execute([$endDate, $startDate, $propertyId, $startDate, $endDate]);
    $bookedNights = (int) ($stmt->fetch()['booked'] ?? 0);

    return $totalNightsAvailable > 0 ? round(($bookedNights / $totalNightsAvailable) * 100, 1) : 0;
}

function getADR($propertyId, $startDate, $endDate) {
    $db = getDB();
    $stmt = $db->prepare("SELECT AVG(rate_per_night) avg_rate FROM reservations r JOIN room_categories rc ON r.category_id = rc.id
        WHERE rc.property_id = ? AND status IN ('confirmed','checked_in','checked_out') AND check_in BETWEEN ? AND ?");
    $stmt->execute([$propertyId, $startDate, $endDate]);
    return round((float) ($stmt->fetch()['avg_rate'] ?? 0), 2);
}

function getRevPAR($propertyId, $startDate, $endDate) {
    $occupancy = getOccupancyRate($propertyId, $startDate, $endDate) / 100;
    $adr = getADR($propertyId, $startDate, $endDate);
    return round($occupancy * $adr, 2);
}

function getRoomImages() {
    $uploadDir = __DIR__ . '/../public/uploads/rooms';
    $files = glob($uploadDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    $images = [];
    if ($files) {
        sort($files); // consistent ordering
        foreach ($files as $f) {
            // rawurlencode handles spaces and special chars in filenames
            $images[] = BASE_URL . '/public/uploads/rooms/' . rawurlencode(basename($f));
        }
    }
    if (empty($images)) {
        $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAMLCwgAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';
        $images = [$placeholder, $placeholder, $placeholder, $placeholder];
    }
    return $images;
}

/**
 * Returns bathroom/toilet images from public/uploads/bathrooms/
 * Each index corresponds to the matching room category (sorted by ID).
 * Upload files named in alphabetical order to match each room.
 */
function getBathroomImages() {
    $uploadDir = __DIR__ . '/../public/uploads/bathrooms';
    $files = glob($uploadDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    $images = [];
    if ($files) {
        sort($files); // consistent ordering matches room order
        foreach ($files as $f) {
            $images[] = BASE_URL . '/public/uploads/bathrooms/' . rawurlencode(basename($f));
        }
    }
    if (empty($images)) {
        // Grey placeholder until real images are uploaded
        $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAMLCwgAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';
        $images = array_fill(0, 7, $placeholder);
    }
    return $images;
}

/**
 * Resolves a database-stored image path, removing any hardcoded subfolder prefixes
 * and prepending the correct BASE_URL.
 */
function getImageUrl($path) {
    if (empty($path)) return '';
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    // Remove hardcoded subfolder prefix if present (e.g. /hotel_website/)
    $cleanPath = preg_replace('#^/hotel_website/#', '/', $path);
    
    // Ensure leading slash is handled correctly relative to BASE_URL
    if (strpos($cleanPath, '/') === 0) {
        return BASE_URL . $cleanPath;
    }
    return BASE_URL . '/' . $cleanPath;
}
