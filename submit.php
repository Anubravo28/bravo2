<?php
ob_clean();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json; charset=utf-8');

// ==========================================================
// 🔒 SAFE CAFE NETWORK LOCKDOWN
// ==========================================================
 
// 1. Paste your cafe's exact Public IP Address inside these quotes
$cafe_ip = '113.199.138.114'; 

// 2. Identify the true client connection IP hidden by cloud proxies
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $visitor_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $visitor_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
} else {
    $visitor_ip = $_SERVER['REMOTE_ADDR'];
}

$visitor_ip = trim($visitor_ip);

// 3. SECURE CHECK: Only block operations if it's a customer action from a wrong IP
$current_action = isset($_POST['action']) ? $_POST['action'] : '';

if ($current_action !== 'cancel_order') {
    if ($visitor_ip !== $cafe_ip) {
        echo json_encode([
            "status" => "error", 
            "message" => "🛑 Access Denied! You must be connected to the Cafe Wi-Fi to place or modify orders."
        ]);
        exit();
    }
}
// ==========================================================

// Database Connection Configurations
$db_host = "sql100.infinityfree.com";
$db_user = "if0_41558613";
$db_pass = "Kakamama28"; 
$db_name = "if0_41558613_whiteangelofficial";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { 
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// ACTION: Handle Order Changes (Admin & Customer Actions)
if (isset($_POST['action'])) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if ($order_id > 0) {
        // Admin Complete Hard-Delete Action
        if ($_POST['action'] === 'cancel_order') {
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error", "message" => $stmt->error]);
            $stmt->close();
        } 
        // Customer Self-Cancel Request
        elseif ($_POST['action'] === 'customer_cancel') {
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) echo json_encode(["status" => "success"]);
            else echo json_encode(["status" => "error", "message" => $stmt->error]);
            $stmt->close();
        }
        // Customer Instruction Note Modifications
        elseif ($_POST['action'] === 'customer_edit') {
            $new_note = isset($_POST['note']) ? $_POST['note'] : '';
            $stmt = $conn->prepare("UPDATE orders SET note = ? WHERE id = ? AND status = 'active'");
            $stmt->bind_param("si", $new_note, $order_id);
            if ($stmt->execute()) echo json_encode(["status" => "success", "new_note" => $new_note]);
            else echo json_encode(["status" => "error", "message" => $stmt->error]);
            $stmt->close();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid order record ID"]);
    }
    $conn->close();
    exit();
}

// ACTION: Handle New Order Submissions
$item = isset($_POST['item']) ? $_POST['item'] : '';
$price = isset($_POST['price']) ? $_POST['price'] : 0;
$note = isset($_POST['note']) ? $_POST['note'] : '';
$table_number = isset($_POST['table_number']) ? $_POST['table_number'] : 'Takeaway';

if (!empty($item)) {
    $stmt = $conn->prepare("INSERT INTO orders (item, price, note, table_number, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("sdss", $item, $price, $note, $table_number);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "item" => $item, "order_id" => $conn->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Cart contents cannot be blank"]);
}

$conn->close();
?>