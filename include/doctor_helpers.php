<?php
// helpers/doctor_helper.php
// ==============================================
// DO NOT put session_start() or DB connection here
// ==============================================

/**
 * Get doctor's schedule (start/end time + slot duration) for a specific date
 * Uses new doctor_schedules table first → falls back to old doctors columns
 *
 * @param mysqli $conn       Database connection
 * @param int    $doctor_id
 * @param string $date       'YYYY-MM-DD'
 * @return array|null        ['start_time' => '09:00:00', 'end_time' => ..., 'slot_duration_min' => 15] or null
 */
function getDoctorScheduleForDate(mysqli $conn, int $doctor_id, string $date): ?array
{
    // Prefer new flexible table (doctor_schedules)
    $stmt = $conn->prepare("
        SELECT 
            start_time,
            end_time,
            slot_duration_min,
            1 AS is_active
        FROM doctor_schedules
        WHERE doctor_id = ?
          AND day_of_week = DAYOFWEEK(?)  -- MySQL: 1=Sunday, 2=Monday, ..., 7=Saturday
          AND is_active = 1
        LIMIT 1
    ");
    // DAYOFWEEK(?): Sunday=1 ... Saturday=7
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row; // Found modern schedule → use it
    }

    // Fallback to old simple columns in doctors table
    $stmt = $conn->prepare("
        SELECT 
            available_time_start AS start_time,
            available_time_end   AS end_time,
            15                   AS slot_duration_min,  // ← default or add column later
            1                    AS is_active
        FROM doctors
        WHERE id = ?
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check if the weekday is actually allowed in the old comma-separated field
        $weekday_short = date('D', strtotime($date)); // 'Mon', 'Tue', 'Sun'...
        $allowed_days_str = $conn->query("SELECT available_days FROM doctors WHERE id = $doctor_id")->fetch_assoc()['available_days'] ?? '';
        $allowed_days = array_map('trim', explode(',', $allowed_days_str));

        if (in_array($weekday_short, $allowed_days)) {
            return $row;
        }
    }

    return null; // No schedule found for this date
}

// You can add more functions here later, e.g.:
// function generateSlotsFromSchedule($schedule, $date, $conn) { ... }
// function isDateUnavailable($conn, $doctor_id, $date) { ... }