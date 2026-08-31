<?php
// ============================================================
// TABLE FUNCTIONS - Restaurant Table Management
// ============================================================

// ============================================================
// 1. GET ALL TABLES
// ============================================================
function getAllTables($conn, $date = null, $time = null) {
    $query = "SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY category, table_number";
    $result = mysqli_query($conn, $query);
    $tables = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tables[] = $row;
    }
    return $tables;
}

// ============================================================
// 2. CHECK TABLE AVAILABILITY
// ============================================================
function isTableAvailable($conn, $table_id, $date, $time, $reservation_id = null) {
    $date = mysqli_real_escape_string($conn, $date);
    $time = mysqli_real_escape_string($conn, $time);
    
    $query = "SELECT COUNT(*) as count FROM reservations 
              WHERE table_id = $table_id 
              AND reservation_date = '$date' 
              AND reservation_time = '$time' 
              AND status != 'cancelled'";
    
    // If updating existing reservation, exclude itself
    if ($reservation_id) {
        $query .= " AND id != $reservation_id";
    }
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return ($row['count'] == 0);
}

// ============================================================
// 3. GET BOOKED TABLES FOR A DATE/TIME
// ============================================================
function getBookedTables($conn, $date, $time) {
    $date = mysqli_real_escape_string($conn, $date);
    $time = mysqli_real_escape_string($conn, $time);
    
    $query = "SELECT table_id FROM reservations 
              WHERE reservation_date = '$date' 
              AND reservation_time = '$time' 
              AND status != 'cancelled'
              AND table_id IS NOT NULL";
    $result = mysqli_query($conn, $query);
    $booked = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $booked[] = $row['table_id'];
    }
    return $booked;
}

// ============================================================
// 4. GET TABLES WITH AVAILABILITY STATUS
// ============================================================
function getTablesWithStatus($conn, $date, $time) {
    $tables = getAllTables($conn);
    $booked = getBookedTables($conn, $date, $time);
    
    $result = [];
    foreach ($tables as $table) {
        $table['is_booked'] = in_array($table['id'], $booked);
        $result[] = $table;
    }
    return $result;
}

// ============================================================
// 5. GET TABLE BY ID
// ============================================================
function getTableById($conn, $table_id) {
    $query = "SELECT * FROM restaurant_tables WHERE id = $table_id AND is_active = 1";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

// ============================================================
// 6. GET CATEGORY LABEL
// ============================================================
function getCategoryLabel($category) {
    $labels = [
        'window' => 'Window Side',
        'couple' => 'Couple Table',
        'vip' => 'VIP Lounge',
        'regular' => 'Regular',
        'outdoor' => 'Outdoor'
    ];
    return $labels[$category] ?? $category;
}

// ============================================================
// 7. GET CATEGORY COLOR
// ============================================================
function getCategoryColor($category) {
    $colors = [
        'window' => '#3498db',
        'couple' => '#2ecc71',
        'vip' => '#e74c3c',
        'regular' => '#f39c12',
        'outdoor' => '#9b59b6'
    ];
    return $colors[$category] ?? '#888';
}