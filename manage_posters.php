<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $response['message'] = 'Poster name is required';
                break;
            }
            
            $stmt = $pdo->prepare("INSERT INTO posters (name) VALUES (?)");
            $stmt->execute([$name]);
            $response['success'] = true;
            $response['id'] = $pdo->lastInsertId();
            $response['message'] = 'Poster added successfully';
            break;
            
        case 'delete':
            $id = $_GET['id'] ?? 0;
            if (!$id) {
                $response['message'] = 'Poster ID is required';
                break;
            }
            
            // Check if poster has job postings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE poster_id = ?");
            $stmt->execute([$id]);
            $job_count = $stmt->fetchColumn();
            
            if ($job_count > 0) {
                // Soft delete - set as inactive
                $stmt = $pdo->prepare("UPDATE posters SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $response['message'] = 'Poster marked as inactive (has job postings)';
            } else {
                // Hard delete - no job postings
                $stmt = $pdo->prepare("DELETE FROM posters WHERE id = ?");
                $stmt->execute([$id]);
                $response['message'] = 'Poster deleted successfully';
            }
            
            $response['success'] = true;
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Unique constraint violation
        $response['message'] = 'Poster name already exists';
    } else {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>