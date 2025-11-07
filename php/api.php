<?php
// Main API router for FunaGig
// Handles all API endpoints for gigs, applicants, messages, etc.

require_once 'config.php';

// Session is started in config.php

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/funagig/php/api.php', '', $path);

// Route the request
switch ($path) {
    case '/login':
        handleLogin();
        break;
    case '/signup':
        handleSignup();
        break;
    case '/logout':
        handleLogout();
        break;
    case '/dashboard':
        handleDashboard();
        break;
    case '/profile':
        handleProfile();
        break;
    case '/gigs':
        handleGigs();
        break;
    case '/gigs/active':
        handleActiveGigs();
        break;
    case '/applications':
        handleApplications();
        break;
    case '/conversations':
        handleConversations();
        break;
    case '/messages':
        handleMessages();
        break;
    // For the missing business pages
    case '/gigs/update':
        handleUpdateGig();
        break;
    case '/gigs/delete':
        handleDeleteGig();
        break;
    case '/applicants':
        handleGetApplicants();
        break;
    case '/applicants/accept':
        handleAcceptApplicant();
        break;
    case '/applicants/reject':
        handleRejectApplicant();
        break;
    case '/saved-gigs':
        handleSavedGigs();
        break;
    case '/notifications':
        handleNotifications();
        break;
    default:
        if (strpos($path, '/messages/') === 0) {
            $conversationId = substr($path, 10);
            handleMessagesByConversation($conversationId);
        } else {
            sendError('Endpoint not found', 404);
        }
        break;
}

// Authentication handlers
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Rate limiting
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!checkRateLimit('login_' . $clientIP, 5, 300)) {
            sendError('Too many login attempts. Please try again later.', 429);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data');
        }
        
        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            sendError('Email and password are required');
        }
        
        if (!validateEmail($email)) {
            sendError('Invalid email format');
        }
        
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id, name, email, password, type, university, major, industry FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user || !verifyPassword($password, $user['password'])) {
            sendError('Invalid credentials');
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        unset($user['password']);
        
        sendResponse([
            'success' => true,
            'user' => $user,
            'userType' => $user['type']
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        sendError('An error occurred during login. Please try again.');
    }
}

function handleSignup() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Rate limiting
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!checkRateLimit('signup_' . $clientIP, 3, 300)) {
            sendError('Too many signup attempts. Please try again later.', 429);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data');
        }
        
        $role = sanitizeInput($input['role'] ?? '');
        $name = sanitizeInput($input['name'] ?? '');
        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirmPassword'] ?? '';
        
        // Validation
        if (empty($role) || empty($name) || empty($email) || empty($password)) {
            sendError('All fields are required');
        }
        
        if (!in_array($role, ['student', 'business'])) {
            sendError('Invalid role');
        }
        
        if (!validateEmail($email)) {
            sendError('Invalid email format');
        }
        
        if ($password !== $confirmPassword) {
            sendError('Passwords do not match');
        }
        
        if (strlen($password) < 6) {
            sendError('Password must be at least 6 characters');
        }
        
        // Additional validation for role-specific fields
        if ($role === 'student') {
            if (empty($input['university']) || empty($input['major'])) {
                sendError('University and major are required for students');
            }
        } else if ($role === 'business') {
            if (empty($input['industry'])) {
                sendError('Industry is required for businesses');
            }
        }
        
        // Check if email already exists
        $db = Database::getInstance();
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existingUser) {
            sendError('Email already registered');
        }
        
        // Create user
        $hashedPassword = hashPassword($password);
        $userId = $db->insert(
            "INSERT INTO users (name, email, password, type, university, major, industry, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $name,
                $email,
                $hashedPassword,
                $role,
                $input['university'] ?? null,
                $input['major'] ?? null,
                $input['industry'] ?? null
            ]
        );
        
        if (!$userId) {
            sendError('Failed to create account');
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Account created successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        sendError('An error occurred during signup. Please try again.');
    }
}

function handleLogout() {
    session_destroy();
    sendResponse(['success' => true]);
}

// Dashboard handler
function handleDashboard() {
    requireAuth();
    
    $user = getCurrentUser();
    $db = Database::getInstance();
    
    if ($user['type'] === 'student') {
        $stats = getStudentStats($db, $user['id']);
    } else {
        $stats = getBusinessStats($db, $user['id']);
    }
    
    sendResponse([
        'success' => true,
        'stats' => $stats
    ]);
}

function getStudentStats($db, $userId) {
    $stats = [];
    
    // Active applications
    $stats['active_tasks'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM applications WHERE user_id = ? AND status = 'accepted'",
        [$userId]
    )['count'];
    
    // Pending applications
    $stats['pending_tasks'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM applications WHERE user_id = ? AND status = 'pending'",
        [$userId]
    )['count'];
    
    // Completed tasks
    $stats['completed_tasks'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM applications WHERE user_id = ? AND status = 'completed'",
        [$userId]
    )['count'];
    
    // Total earned
    $stats['total_earned'] = $db->fetchOne(
        "SELECT COALESCE(SUM(g.budget), 0) as total FROM applications a 
         JOIN gigs g ON a.gig_id = g.id 
         WHERE a.user_id = ? AND a.status = 'completed'",
        [$userId]
    )['total'];
    
    return $stats;
}

function getBusinessStats($db, $userId) {
    $stats = [];
    
    // Active gigs
    $stats['active_gigs'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM gigs WHERE user_id = ? AND status = 'active'",
        [$userId]
    )['count'];
    
    // Total applicants
    $stats['total_applicants'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM applications a 
         JOIN gigs g ON a.gig_id = g.id 
         WHERE g.user_id = ?",
        [$userId]
    )['count'];
    
    // Hired students
    $stats['hired_students'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM applications a 
         JOIN gigs g ON a.gig_id = g.id 
         WHERE g.user_id = ? AND a.status = 'accepted'",
        [$userId]
    )['count'];
    
    return $stats;
}

// Profile handler
function handleProfile() {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = getCurrentUser();
        sendResponse(['success' => true, 'user' => $user]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $db = Database::getInstance();
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $updateValues = [];
        
        if (isset($input['name'])) {
            $updateFields[] = 'name = ?';
            $updateValues[] = sanitizeInput($input['name']);
        }
        
        if (isset($input['university'])) {
            $updateFields[] = 'university = ?';
            $updateValues[] = sanitizeInput($input['university']);
        }
        
        if (isset($input['major'])) {
            $updateFields[] = 'major = ?';
            $updateValues[] = sanitizeInput($input['major']);
        }
        
        if (isset($input['industry'])) {
            $updateFields[] = 'industry = ?';
            $updateValues[] = sanitizeInput($input['industry']);
        }
        
        if (isset($input['location'])) {
            $updateFields[] = 'location = ?';
            $updateValues[] = sanitizeInput($input['location']);
        }
        
        if (isset($input['phone'])) {
            $updateFields[] = 'phone = ?';
            $updateValues[] = sanitizeInput($input['phone']);
        }
        
        if (isset($input['website'])) {
            $updateFields[] = 'website = ?';
            $updateValues[] = sanitizeInput($input['website']);
        }
        
        if (isset($input['bio'])) {
            $updateFields[] = 'bio = ?';
            $updateValues[] = sanitizeInput($input['bio']);
        }
        
        if (isset($input['skills'])) {
            $updateFields[] = 'skills = ?';
            $updateValues[] = sanitizeInput($input['skills']);
        }
        
        if (empty($updateFields)) {
            sendError('No fields to update');
            return;
        }
        
        $updateValues[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $db->update($sql, $updateValues);
        
        sendResponse(['success' => true, 'message' => 'Profile updated']);
    }
}

// Gigs handlers
function handleGigs() {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $db = Database::getInstance();
        $gigs = $db->fetchAll(
            "SELECT g.*, u.name as business_name FROM gigs g 
             JOIN users u ON g.user_id = u.id 
             WHERE g.status = 'active' 
             ORDER BY g.created_at DESC"
        );
        
        sendResponse(['success' => true, 'gigs' => $gigs]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendError('Invalid JSON data');
            }
            
            $userId = $_SESSION['user_id'];
            
            // Validate required fields
            $requiredFields = ['title', 'description', 'budget', 'deadline'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    sendError("Field '$field' is required");
                }
            }
            
            // Validate budget is numeric and positive
            if (!is_numeric($input['budget']) || $input['budget'] <= 0) {
                sendError('Budget must be a positive number');
            }
            
            // Validate deadline is in the future
            if (strtotime($input['deadline']) <= time()) {
                sendError('Deadline must be in the future');
            }
            
            $db = Database::getInstance();
            $gigId = $db->insert(
                "INSERT INTO gigs (user_id, title, description, budget, deadline, skills, location, type, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
                [
                    $userId,
                    sanitizeInput($input['title'] ?? ''),
                    sanitizeInput($input['description'] ?? ''),
                    $input['budget'] ?? 0,
                    $input['deadline'] ?? '',
                    sanitizeInput($input['skills'] ?? ''),
                    sanitizeInput($input['location'] ?? ''),
                    sanitizeInput($input['type'] ?? '')
                ]
            );
            
            if (!$gigId) {
                sendError('Failed to create gig');
            }
            
            sendResponse(['success' => true, 'gig_id' => $gigId]);
            
        } catch (Exception $e) {
            error_log("Gig creation error: " . $e->getMessage());
            sendError('An error occurred while creating the gig. Please try again.');
        }
    }
}

function handleActiveGigs() {
    requireAuth();
    
    $user = getCurrentUser();
    $db = Database::getInstance();
    
    if ($user['type'] === 'business') {
        $gigs = $db->fetchAll(
            "SELECT g.*, 
             (SELECT COUNT(*) FROM applications WHERE gig_id = g.id) as applicant_count,
             (SELECT COUNT(*) FROM applications WHERE gig_id = g.id AND status = 'accepted') as hired_count
             FROM gigs g 
             WHERE g.user_id = ? AND g.status = 'active' 
             ORDER BY g.created_at DESC",
            [$user['id']]
        );
    } else {
        $gigs = $db->fetchAll(
            "SELECT g.*, u.name as business_name FROM gigs g 
             JOIN users u ON g.user_id = u.id 
             WHERE g.status = 'active' 
             ORDER BY g.created_at DESC"
        );
    }
    
    sendResponse(['success' => true, 'gigs' => $gigs]);
}

// Applications handler
function handleApplications() {
    requireAuth();
    
    $userId = $_SESSION['user_id'];
    $user = getCurrentUser();
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get status filter from query string
        $status = $_GET['status'] ?? null;
        
        if ($user['type'] === 'student') {
            // Student viewing their own applications
            $sql = "SELECT a.*, g.title as gig_title, g.budget, u.name as business_name 
                    FROM applications a 
                    JOIN gigs g ON a.gig_id = g.id 
                    JOIN users u ON g.user_id = u.id 
                    WHERE a.user_id = ?";
            $params = [$userId];
            
            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY a.applied_at DESC";
            
            $applications = $db->fetchAll($sql, $params);
            sendResponse(['success' => true, 'applications' => $applications]);
        } else {
            // Business viewing applications for their gigs
            $gigId = $_GET['gig_id'] ?? null;
            
            $sql = "SELECT a.*, g.title as gig_title, u.name as student_name, 
                    u.university, u.major, u.skills
                    FROM applications a
                    JOIN gigs g ON a.gig_id = g.id
                    JOIN users u ON a.user_id = u.id
                    WHERE g.user_id = ?";
            $params = [$userId];
            
            if ($gigId) {
                $sql .= " AND g.id = ?";
                $params[] = $gigId;
            }
            
            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY a.applied_at DESC";
            
            $applications = $db->fetchAll($sql, $params);
            sendResponse(['success' => true, 'applications' => $applications]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if already applied
        $existing = $db->fetchOne(
            "SELECT id FROM applications WHERE user_id = ? AND gig_id = ?",
            [$userId, $input['gig_id']]
        );
        
        if ($existing) {
            sendError('Already applied to this gig');
        }
        
        $applicationId = $db->insert(
            "INSERT INTO applications (user_id, gig_id, message, status, applied_at) 
             VALUES (?, ?, ?, 'pending', NOW())",
            [
                $userId,
                $input['gig_id'],
                sanitizeInput($input['message'] ?? '')
            ]
        );
        
        // Get gig owner to notify
        $gig = $db->fetchOne("SELECT user_id, title FROM gigs WHERE id = ?", [$input['gig_id']]);
        if ($gig) {
            $student = getCurrentUser();
            createNotification(
                $gig['user_id'],
                'New Application',
                $student['name'] . ' applied to your gig: ' . $gig['title'],
                'info'
            );
        }
        
        sendResponse(['success' => true, 'application_id' => $applicationId]);
    } else {
        sendError('Method not allowed', 405);
    }
}

// Messaging handlers
function handleConversations() {
    requireAuth();
    
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $conversations = $db->fetchAll(
            "SELECT c.*, 
             CASE 
                 WHEN c.user1_id = ? THEN u2.id 
                 ELSE u1.id 
             END as other_user_id,
             CASE 
                 WHEN c.user1_id = ? THEN u2.name 
                 ELSE u1.name 
             END as other_user_name,
             CASE 
                 WHEN c.user1_id = ? THEN u2.email 
                 ELSE u1.email 
             END as other_user_email,
             CASE 
                 WHEN c.user1_id = ? THEN u2.university 
                 ELSE u1.university 
             END as other_user_university,
             CASE 
                 WHEN c.user1_id = ? THEN u2.major 
                 ELSE u1.major 
             END as other_user_major,
             (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
             (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
             (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = FALSE) as unread_count
             FROM conversations c
             JOIN users u1 ON c.user1_id = u1.id
             JOIN users u2 ON c.user2_id = u2.id
             WHERE c.user1_id = ? OR c.user2_id = ?
             ORDER BY COALESCE(last_message_time, c.created_at) DESC, c.created_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
        
        sendResponse(['success' => true, 'conversations' => $conversations]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conversationId = $db->insert(
            "INSERT INTO conversations (user1_id, user2_id, created_at) VALUES (?, ?, NOW())",
            [$userId, $input['user_id']]
        );
        
        sendResponse(['success' => true, 'conversation_id' => $conversationId]);
    }
}

function handleMessages() {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $db = Database::getInstance();
        
        // Get conversation to find recipient
        $conversation = $db->fetchOne(
            "SELECT user1_id, user2_id FROM conversations WHERE id = ?",
            [$input['conversation_id']]
        );
        
        $messageId = $db->insert(
            "INSERT INTO messages (conversation_id, sender_id, content, created_at) VALUES (?, ?, ?, NOW())",
            [
                $input['conversation_id'],
                $userId,
                sanitizeInput($input['message'])
            ]
        );
        
        // Notify recipient
        if ($conversation && $messageId) {
            $recipientId = $conversation['user1_id'] == $userId ? $conversation['user2_id'] : $conversation['user1_id'];
            $sender = getCurrentUser();
            createNotification(
                $recipientId,
                'New Message',
                $sender['name'] . ' sent you a message',
                'info'
            );
        }
        
        sendResponse(['success' => true, 'message_id' => $messageId]);
    }
}

function handleMessagesByConversation($conversationId) {
    requireAuth();
    
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();
    
    // Mark messages as read when loading conversation
    $db->update(
        "UPDATE messages SET is_read = TRUE 
         WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE",
        [$conversationId, $userId]
    );
    
    $messages = $db->fetchAll(
        "SELECT m.*, u.name as sender_name FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC",
        [$conversationId]
    );
    
    sendResponse(['success' => true, 'messages' => $messages]);
}
//Extra stuff am not so sure about
function handleUpdateGig() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $gigId = $input['gig_id'];
    $userId = $_SESSION['user_id'];
    
    // Validate required fields
    if (empty($input['title']) || empty($input['description']) || empty($input['budget']) || empty($input['deadline'])) {
        sendError('All required fields must be filled');
        return;
    }
    
    $db = Database::getInstance();
    $affected = $db->update(
        "UPDATE gigs SET title=?, description=?, budget=?, deadline=?, status=?, type=?, skills=?, location=? 
         WHERE id=? AND user_id=?",
        [
            sanitizeInput($input['title']),
            sanitizeInput($input['description']),
            $input['budget'],
            $input['deadline'],
            $input['status'] ?? 'active',
            sanitizeInput($input['type'] ?? 'one-time'),
            sanitizeInput($input['skills'] ?? ''),
            sanitizeInput($input['location'] ?? 'Remote'),
            $gigId,
            $userId
        ]
    );
    
    sendResponse(['success' => $affected > 0]);
}

function handleDeleteGig() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $gigId = $input['gig_id'];
    $userId = $_SESSION['user_id'];
    
    $db = Database::getInstance();
    $affected = $db->delete(
        "DELETE FROM gigs WHERE id=? AND user_id=?",
        [$gigId, $userId]
    );
    
    sendResponse(['success' => $affected > 0]);
}

function handleGetApplicants() {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $gigId = $_GET['gig_id'] ?? null;
    
    $db = Database::getInstance();
    $sql = "SELECT a.*, g.title as gig_title, u.name as student_name, 
            u.university, u.major, u.skills
            FROM applications a
            JOIN gigs g ON a.gig_id = g.id
            JOIN users u ON a.user_id = u.id
            WHERE g.user_id = ?";
    
    $params = [$userId];
    if ($gigId) {
        $sql .= " AND g.id = ?";
        $params[] = $gigId;
    }
    
    $applicants = $db->fetchAll($sql, $params);
    sendResponse(['success' => true, 'applicants' => $applicants]);
}

function handleAcceptApplicant() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $applicationId = $input['application_id'];
    
    $db = Database::getInstance();
    
    // Get application details before updating
    $application = $db->fetchOne(
        "SELECT a.user_id, a.gig_id, g.title as gig_title FROM applications a 
         JOIN gigs g ON a.gig_id = g.id 
         WHERE a.id = ?",
        [$applicationId]
    );
    
    $affected = $db->update(
        "UPDATE applications SET status='accepted', responded_at=NOW() 
         WHERE id=?",
        [$applicationId]
    );
    
    // Notify student
    if ($affected > 0 && $application) {
        createNotification(
            $application['user_id'],
            'Application Accepted! 🎉',
            'Your application for "' . $application['gig_title'] . '" has been accepted!',
            'success'
        );
    }
    
    sendResponse(['success' => $affected > 0]);
}

function handleRejectApplicant() {
    requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $applicationId = $input['application_id'];
    
    $db = Database::getInstance();
    
    // Get application details before updating
    $application = $db->fetchOne(
        "SELECT a.user_id, a.gig_id, g.title as gig_title FROM applications a 
         JOIN gigs g ON a.gig_id = g.id 
         WHERE a.id = ?",
        [$applicationId]
    );
    
    $affected = $db->update(
        "UPDATE applications SET status='rejected', responded_at=NOW() 
         WHERE id=?",
        [$applicationId]
    );
    
    // Notify student
    if ($affected > 0 && $application) {
        createNotification(
            $application['user_id'],
            'Application Update',
            'Your application for "' . $application['gig_title'] . '" was not selected at this time.',
            'info'
        );
    }
    
    sendResponse(['success' => $affected > 0]);
}

// Saved gigs handler
function handleSavedGigs() {
    requireAuth();
    
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get saved gigs for the user
        $savedGigs = $db->fetchAll(
            "SELECT g.*, sg.saved_at, u.name as business_name
             FROM saved_gigs sg
             JOIN gigs g ON sg.gig_id = g.id
             JOIN users u ON g.user_id = u.id
             WHERE sg.user_id = ? AND g.status = 'active'
             ORDER BY sg.saved_at DESC",
            [$userId]
        );
        
        sendResponse(['success' => true, 'saved_gigs' => $savedGigs]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save a gig
        $input = json_decode(file_get_contents('php://input'), true);
        $gigId = $input['gig_id'] ?? null;
        
        if (!$gigId) {
            sendError('Gig ID is required');
            return;
        }
        
        // Check if already saved
        $existing = $db->fetchOne(
            "SELECT id FROM saved_gigs WHERE user_id = ? AND gig_id = ?",
            [$userId, $gigId]
        );
        
        if ($existing) {
            sendError('Gig is already saved');
            return;
        }
        
        $savedId = $db->insert(
            "INSERT INTO saved_gigs (user_id, gig_id, saved_at) VALUES (?, ?, NOW())",
            [$userId, $gigId]
        );
        
        sendResponse(['success' => true, 'saved_id' => $savedId]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Unsave a gig - can use query parameter or request body
        $gigId = $_GET['gig_id'] ?? null;
        
        if (!$gigId) {
            // Try to get from request body
            $input = json_decode(file_get_contents('php://input'), true);
            $gigId = $input['gig_id'] ?? null;
        }
        
        if (!$gigId) {
            sendError('Gig ID is required');
            return;
        }
        
        $affected = $db->delete(
            "DELETE FROM saved_gigs WHERE user_id = ? AND gig_id = ?",
            [$userId, $gigId]
        );
        
        sendResponse(['success' => $affected > 0]);
    } else {
        sendError('Method not allowed', 405);
    }
}

// Notifications handler
function handleNotifications() {
    requireAuth();
    
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all notifications for user
        $isRead = $_GET['is_read'] ?? null;
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($isRead !== null) {
            $sql .= " AND is_read = ?";
            $params[] = $isRead === 'true' ? 1 : 0;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $notifications = $db->fetchAll($sql, $params);
        
        // Get unread count
        $unreadCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE",
            [$userId]
        )['count'];
        
        sendResponse([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Mark notification(s) as read
        $input = json_decode(file_get_contents('php://input'), true);
        $notificationId = $input['notification_id'] ?? null;
        $markAll = $input['mark_all'] ?? false;
        
        if ($markAll) {
            // Mark all as read
            $affected = $db->update(
                "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE",
                [$userId]
            );
        } elseif ($notificationId) {
            // Mark specific notification as read
            $affected = $db->update(
                "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?",
                [$notificationId, $userId]
            );
        } else {
            sendError('Notification ID or mark_all is required');
            return;
        }
        
        sendResponse(['success' => $affected > 0]);
    } else {
        sendError('Method not allowed', 405);
    }
}

// Helper function to create a notification
function createNotification($userId, $title, $message, $type = 'info') {
    $db = Database::getInstance();
    return $db->insert(
        "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())",
        [$userId, sanitizeInput($title), sanitizeInput($message), $type]
    );
}
?>

