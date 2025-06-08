<?php
// Directory where patches will be saved
$save_directory = 'patches/';

// Ensure the directory exists, create it if not, and check permissions
if (!is_dir($save_directory)) {
    if (!mkdir($save_directory, 0755, true)) { // 0755 gives rwx for owner, rx for group and others
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create patch directory. Check server permissions.']);
        exit;
    }
} else if (!is_writable($save_directory)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Patch directory is not writable. Check server permissions.']);
    exit;
}

// Get the raw POST data
$raw_post_data = file_get_contents('php://input');

// Decode the JSON data
$decoded_data = json_decode($raw_post_data, true);

// Check if JSON decoding was successful and if the expected keys are present
if ($decoded_data === null || !isset($decoded_data['filename']) || !isset($decoded_data['patchData'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data or missing keys (filename, patchData).']);
    exit;
}

// Sanitize the filename (optional, but good practice)
// For this specific format 'phuYYYYMMDD_HHMMSS.json', basic validation is enough.
$filename = basename($decoded_data['filename']); // basename() helps prevent directory traversal
if (!preg_match('/^phu\d{8}_\d{6}\.json$/', $filename)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid filename format. Expected phuYYYYMMDD_HHMMSS.json.']);
    exit;
}

$file_path = $save_directory . $filename;

// Convert patchData back to JSON string for saving
// The client already sends patchData as an object/array, so we re-encode it.
$json_to_save = json_encode($decoded_data['patchData'], JSON_PRETTY_PRINT);

if ($json_to_save === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to re-encode patch data to JSON.']);
    exit;
}

// Save the JSON data to the file
if (file_put_contents($file_path, $json_to_save)) {
    // Construct the URL of the saved file
    // This assumes the 'patches' directory is accessible from the web root.
    // Adjust if your server setup is different.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['PHP_SELF']); // Gets the directory of the current script

    // Ensure script_dir doesn't add an unwanted leading slash if it's already at the root
    // or correctly navigate if it's in a subdirectory.
    $base_url_path = rtrim($script_dir, '/');
    if ($base_url_path === '' || $base_url_path === DIRECTORY_SEPARATOR) {
        // If script is in root, path to patches is just /patches/
        $url_of_saved_file = $protocol . $host . '/' . $save_directory . $filename;
    } else {
        // If script is in a sub-directory, form path relative to that
        $url_of_saved_file = $protocol . $host . $base_url_path . '/' . $save_directory . $filename;
    }
    // A simpler relative URL as requested by "publish url ./phuTIMESTAMP"
    $relative_url = './' . $save_directory . $filename;


    http_response_code(200); // OK
    echo json_encode([
        'status' => 'success',
        'message' => 'Patch saved successfully.',
        'filename' => $filename,
        'url' => $relative_url // Send back the relative URL
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to save patch file. Check server permissions or disk space.']);
}

?>
