<?php
// Main API router for FunaGig
// Handles all API endpoints for gigs, applicants, messages, etc.

require_once 'config.php';

// Session is started in config.php

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/php/api.php', '', $path);

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
    case '/gigs/saved':
        handleSavedGigs();
        break;
    case '/gigs/posted':
        handlePostedGigs();
        break;
    case '/gigs/active':
        handleActiveGigs();
        break;
    case '/applications':
        handleApplyToGig();
        break;
    case '/applicants':
        handleApplicants();
        break;
    case '/analytics':
        handleAnalytics();
        break;
    case '/notifications':
        handleNotifications();
        break;
    case '/conversations':
        handleConversations();
        break;
    case '/messages':
        handleMessages();
        break;
    case '/contact':
        handleContact();
        break;
    case '/forgot-password':
        handleForgotPassword();
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
        $db->update(
            "UPDATE users SET name = ?, university = ?, major = ?, industry = ? WHERE id = ?",
            [
                sanitizeInput($input['name'] ?? ''),
                sanitizeInput($input['university'] ?? ''),
                sanitizeInput($input['major'] ?? ''),
                sanitizeInput($input['industry'] ?? ''),
                $userId
            ]
        );
        
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
            
            // Notify students
            $students = $db->fetchAll("SELECT id FROM users WHERE type = 'student'");
            foreach ($students as $student) {
                createNotification($student['id'], 'New Gig Posted', "A new gig has been posted: " . sanitizeInput($input['title'] ?? ''));
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
function handleApplicants() {
    requireAuth();

    $user = getCurrentUser();
    $db = Database::getInstance();

    if ($user['type'] === 'business') {
        $applicants = $db->fetchAll(
            "SELECT a.*, u.name as student_name, g.title as gig_title
             FROM applications a
             JOIN users u ON a.user_id = u.id
             JOIN gigs g ON a.gig_id = g.id
             WHERE g.user_id = ?",
            [$user['id']]
        );
    } else {
        sendError('Unauthorized', 403);
    }

    sendResponse(['success' => true, 'applicants' => $applicants]);
}

function createNotification($userId, $title, $message) {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())",
        [$userId, $title, $message]
    );
}

function handleNotifications() {
    requireAuth();
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];

    $notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
    sendResponse(['success' => true, 'notifications' => $notifications]);
}

function handleAnalytics() {
    requireAuth();

    $user = getCurrentUser();
    $db = Database::getInstance();

    if ($user['type'] === 'business') {
        $total_views = $db->fetchOne("SELECT SUM(view_count) as total FROM gigs WHERE user_id = ?", [$user['id']])['total'];
        $total_applicants = $db->fetchOne("SELECT COUNT(a.id) as total FROM applications a JOIN gigs g ON a.gig_id = g.id WHERE g.user_id = ?", [$user['id']])['total'];
        $hired_count = $db->fetchOne("SELECT COUNT(a.id) as total FROM applications a JOIN gigs g ON a.gig_id = g.id WHERE g.user_id = ? AND a.status = 'accepted'", [$user['id']])['total'];
        $hiring_rate = $total_applicants > 0 ? ($hired_count / $total_applicants) * 100 : 0;

        $analytics = [
            'total_views' => $total_views,
            'total_applicants' => $total_applicants,
            'hiring_rate' => round($hiring_rate, 2),
        ];
    } else {
        sendError('Unauthorized', 403);
    }

    sendResponse(['success' => true, 'analytics' => $analytics]);
}

function handleApplyToGig() {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $db = Database::getInstance();
        
        // Check if already applied
        $existing = $db->fetchOne(
            "SELECT id FROM applications WHERE user_id = ? AND gig_id = ?",
            [$userId, $input['gig_id']]
        );
        
        if ($existing) {
            sendError('Already applied to this gig');
        }
        
        $applicationId = $db->insert(
            "INSERT INTO applications (user_id, gig_id, message, status, created_at) 
             VALUES (?, ?, ?, 'pending', NOW())",
            [
                $userId,
                $input['gig_id'],
                sanitizeInput($input['message'] ?? '')
            ]
        );
        
        // Notify business
        $gig = $db->fetchOne("SELECT user_id, title FROM gigs WHERE id = ?", [$input['gig_id']]);
        createNotification($gig['user_id'], 'New Application', "You have a new application for your gig: " . $gig['title']);

        sendResponse(['success' => true, 'application_id' => $applicationId]);
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
                 WHEN c.user1_id = ? THEN u2.name 
                 ELSE u1.name 
             END as other_user_name,
             (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
             (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
             FROM conversations c
             JOIN users u1 ON c.user1_id = u1.id
             JOIN users u2 ON c.user2_id = u2.id
             WHERE c.user1_id = ? OR c.user2_id = ?
             ORDER BY last_message_time DESC",
            [$userId, $userId, $userId]
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

function handleSavedGigs() {
    requireAuth();
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $gigs = $db->fetchAll(
            "SELECT g.* FROM gigs g JOIN saved_gigs sg ON g.id = sg.gig_id WHERE sg.user_id = ?",
            [$userId]
        );
        sendResponse(['success' => true, 'gigs' => $gigs]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $gigId = $input['gig_id'];

        $existing = $db->fetchOne("SELECT id FROM saved_gigs WHERE user_id = ? AND gig_id = ?", [$userId, $gigId]);

        if (!$existing) {
            $db->insert("INSERT INTO saved_gigs (user_id, gig_id) VALUES (?, ?)", [$userId, $gigId]);
        }
        sendResponse(['success' => true]);
    }
}

function handlePostedGigs() {
    requireAuth();

    $user = getCurrentUser();
    $db = Database::getInstance();

    if ($user['type'] === 'business') {
        $gigs = $db->fetchAll(
            "SELECT g.*,
             (SELECT COUNT(*) FROM applications WHERE gig_id = g.id) as applicant_count
             FROM gigs g
             WHERE g.user_id = ?
             ORDER BY g.created_at DESC",
            [$user['id']]
        );
    } else {
        sendError('Unauthorized', 403);
    }

    sendResponse(['success' => true, 'gigs' => $gigs]);
}

function handleMessages() {
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        $db = Database::getInstance();
        $messageId = $db->insert(
            "INSERT INTO messages (conversation_id, sender_id, content, created_at) VALUES (?, ?, ?, NOW())",
            [
                $input['conversation_id'],
                $userId,
                sanitizeInput($input['message'])
            ]
        );
        
        sendResponse(['success' => true, 'message_id' => $messageId]);
    }
}

function handleForgotPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = sanitizeInput($input['email'] ?? '');

        if (empty($email) || !validateEmail($email)) {
            sendError('A valid email is required');
        }

        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);

        if ($user) {
            $token = generateToken();
            // In a real app, you'd store this token and email it to the user.
            error_log("Password reset for $email, token: $token");
        }

        // Always return success to prevent user enumeration
        sendResponse(['success' => true, 'message' => 'If your email is in our system, you will receive a password reset link.']);

    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        sendError('An error occurred. Please try again.');
    }
}

function handleContact() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $name = sanitizeInput($input['name'] ?? '');
        $email = sanitizeInput($input['email'] ?? '');
        $subject = sanitizeInput($input['subject'] ?? '');
        $message = sanitizeInput($input['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            sendError('All fields are required');
        }

        if (!validateEmail($email)) {
            sendError('Invalid email format');
        }

        // In a real application, you would send an email here.
        // For this example, we'll just log it.
        error_log("Contact form submission: Name: $name, Email: $email, Subject: $subject, Message: $message");

        sendResponse(['success' => true, 'message' => 'Message sent successfully']);

    } catch (Exception $e) {
        error_log("Contact form error: " . $e->getMessage());
        sendError('An error occurred while sending the message. Please try again.');
    }
}

function handleMessagesByConversation($conversationId) {
    requireAuth();
    
    $db = Database::getInstance();
    $messages = $db->fetchAll(
        "SELECT m.*, u.name as sender_name FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC",
        [$conversationId]
    );
    
    sendResponse(['success' => true, 'messages' => $messages]);
}
?>

