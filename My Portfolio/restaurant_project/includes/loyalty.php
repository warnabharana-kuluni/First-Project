<?php
// ============================================================
// LOYALTY SYSTEM - Functions for Points Management
// ============================================================

// ============================================================
// 1. ADD LOYALTY POINTS FOR A RESERVATION
// ============================================================
function addLoyaltyPoints($conn, $customer_id, $reservation_id, $total_amount) {
    if (!$customer_id || $customer_id <= 0) return false;
    
    // Calculate points: 1 point per 100 LKR (minimum 5 points)
    $points = max(5, floor($total_amount / 100));
    
    // Also give bonus points for reservations (10 bonus points per booking)
    $points += 10;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update customer's loyalty points
        $update_customer = "UPDATE customers SET 
                            loyalty_points = loyalty_points + $points,
                            total_points_earned = total_points_earned + $points
                            WHERE id = $customer_id";
        mysqli_query($conn, $update_customer);
        
        // Insert transaction record
        $insert_trans = "INSERT INTO loyalty_transactions 
                         (customer_id, reservation_id, points, type, description, created_at) 
                         VALUES 
                         ($customer_id, $reservation_id, $points, 'earned', 
                          'Points earned for reservation #$reservation_id (LKR " . number_format($total_amount, 2) . ")', 
                          NOW())";
        mysqli_query($conn, $insert_trans);
        
        // Update reservation with points earned
        $update_res = "UPDATE reservations SET points_earned = $points WHERE id = $reservation_id";
        mysqli_query($conn, $update_res);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// ============================================================
// 2. REDEEM LOYALTY POINTS
// ============================================================
function redeemLoyaltyPoints($conn, $customer_id, $points_to_redeem, $reservation_id = null) {
    if (!$customer_id || $customer_id <= 0 || $points_to_redeem <= 0) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }
    
    // Get current points
    $points_query = "SELECT loyalty_points FROM customers WHERE id = $customer_id";
    $result = mysqli_query($conn, $points_query);
    $row = mysqli_fetch_assoc($result);
    $current_points = $row['loyalty_points'] ?? 0;
    
    if ($points_to_redeem > $current_points) {
        return ['success' => false, 'message' => "You only have $current_points points available."];
    }
    
    // Minimum redemption: 50 points
    if ($points_to_redeem < 50) {
        return ['success' => false, 'message' => 'Minimum redemption is 50 points.'];
    }
    
    // Maximum redemption: 1000 points per booking
    if ($points_to_redeem > 1000) {
        return ['success' => false, 'message' => 'Maximum redemption is 1000 points per booking.'];
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update customer's points
        $update_customer = "UPDATE customers SET 
                            loyalty_points = loyalty_points - $points_to_redeem,
                            total_points_redeemed = total_points_redeemed + $points_to_redeem
                            WHERE id = $customer_id";
        mysqli_query($conn, $update_customer);
        
        // Insert transaction record
        $desc = "Points redeemed for ";
        $desc .= $reservation_id ? "reservation #$reservation_id" : "discount";
        $insert_trans = "INSERT INTO loyalty_transactions 
                         (customer_id, reservation_id, points, type, description, created_at) 
                         VALUES 
                         ($customer_id, " . ($reservation_id ? $reservation_id : 'NULL') . ", 
                          -$points_to_redeem, 'redeemed', '$desc', NOW())";
        mysqli_query($conn, $insert_trans);
        
        // Update reservation if provided
        if ($reservation_id) {
            $update_res = "UPDATE reservations SET points_redeemed = points_redeemed + $points_to_redeem WHERE id = $reservation_id";
            mysqli_query($conn, $update_res);
        }
        
        mysqli_commit($conn);
        
        $discount = $points_to_redeem; // 1 point = 1 LKR
        return ['success' => true, 'message' => "Successfully redeemed $points_to_redeem points (LKR $discount.00 discount!)", 'discount' => $discount];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Error processing redemption: ' . $e->getMessage()];
    }
}

// ============================================================
// 3. GET CUSTOMER LOYALTY INFO
// ============================================================
function getCustomerLoyaltyInfo($conn, $customer_id) {
    if (!$customer_id || $customer_id <= 0) {
        return ['points' => 0, 'total_earned' => 0, 'total_redeemed' => 0];
    }
    
    $query = "SELECT loyalty_points, total_points_earned, total_points_redeemed 
              FROM customers WHERE id = $customer_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    return [
        'points' => (int)($row['loyalty_points'] ?? 0),
        'total_earned' => (int)($row['total_points_earned'] ?? 0),
        'total_redeemed' => (int)($row['total_points_redeemed'] ?? 0)
    ];
}

// ============================================================
// 4. GET CUSTOMER POINTS HISTORY
// ============================================================
function getPointsHistory($conn, $customer_id, $limit = 20) {
    if (!$customer_id || $customer_id <= 0) {
        return [];
    }
    
    $query = "SELECT * FROM loyalty_transactions 
              WHERE customer_id = $customer_id 
              ORDER BY created_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    return $history;
}

// ============================================================
// 5. CALCULATE AVAILABLE DISCOUNT
// ============================================================
function calculateAvailableDiscount($customer_id, $conn) {
    $info = getCustomerLoyaltyInfo($conn, $customer_id);
    $points = $info['points'];
    
    // 1 point = 1 LKR, but max 500 LKR discount per booking
    $max_discount = min($points, 500);
    return $max_discount;
}