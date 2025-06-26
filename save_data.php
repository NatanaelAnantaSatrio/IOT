<?php
// --- Database Configuration ---
$servername = "localhost"; // Or your database server IP
$username = "root";     // Your database username
$password = "";     // Your database password
$dbname = "sensor_database"; // Your database name

// --- Set Header to JSON ---
header("Content-Type: application/json; charset=UTF-8");

// --- Create connection ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Check connection ---
if ($conn->connect_error) {
    // If connection fails, return an error message
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    die();
}

// --- Get the data from the POST request ---
// The dashboard will send the data in the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

// --- Validate received data ---
if (is_null($data) || !isset($data->suhu) || !isset($data->kelembaban) || !isset($data->lm35) || !isset($data->info)) {
    // If data is invalid or incomplete, return an error
    echo json_encode(['success' => false, 'message' => 'Invalid or incomplete data received.']);
    die();
}

// --- Prepare and bind the SQL statement to prevent SQL injection ---
$stmt = $conn->prepare("INSERT INTO sensor_data (suhu, kelembaban, lm35, info) VALUES (?, ?, ?, ?)");
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
    die();
}

// Bind parameters: 'ddds' means two doubles, one double, and one string
$stmt->bind_param("ddds", $data->suhu, $data->kelembaban, $data->lm35, $data->info);

// --- Execute the statement and check for success ---
if ($stmt->execute()) {
    // If successful, return a success message
    echo json_encode(['success' => true, 'message' => 'New record created successfully']);
} else {
    // If execution fails, return an error message
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}

// --- Close the statement and connection ---
$stmt->close();
$conn->close();
