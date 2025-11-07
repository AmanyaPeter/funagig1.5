-- FunaGig Database Schema
-- Unified database structure for users, gigs, applications, messages
-- Compatible with MySQL/MariaDB for XAMPP deployment

-- Create database
CREATE DATABASE IF NOT EXISTS funagig;
USE funagig;

-- Users table (unified for students and businesses)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    type ENUM('student', 'business') NOT NULL,
    university VARCHAR(255) NULL,
    major VARCHAR(255) NULL,
    industry VARCHAR(255) NULL,
    profile_image VARCHAR(500) NULL,
    bio TEXT NULL,
    skills TEXT NULL,
    location VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    website VARCHAR(255) NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Gigs table (jobs/projects posted by businesses)
CREATE TABLE gigs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    budget DECIMAL(10,2) NOT NULL,
    deadline DATE NOT NULL,
    skills TEXT NULL,
    location VARCHAR(255) NULL,
    type ENUM('one-time', 'ongoing', 'contract') DEFAULT 'one-time',
    status ENUM('active', 'paused', 'completed', 'cancelled') DEFAULT 'active',
    view_count INT DEFAULT 0,
    application_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Applications table (student applications to gigs)
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gig_id INT NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    rating INT NULL,
    review TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gig_id) REFERENCES gigs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (user_id, gig_id)
);

-- Conversations table (messaging between users)
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conversation (user1_id, user2_id)
);

-- Messages table (individual messages in conversations)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table (system notifications)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Saved gigs table (students can save gigs for later)
CREATE TABLE saved_gigs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gig_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gig_id) REFERENCES gigs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved_gig (user_id, gig_id)
);

-- Skills table (for skill management)
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    category VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User skills table (many-to-many relationship)
CREATE TABLE user_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_skill (user_id, skill_id)
);

-- Reviews table (ratings and reviews)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    application_id INT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    UNIQUE KEY unique_review (reviewer_id, reviewee_id, application_id)
);

-- Categories table (for gig categorization)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Gig categories table (many-to-many relationship)
CREATE TABLE gig_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gig_id INT NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (gig_id) REFERENCES gigs(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_gig_category (gig_id, category_id)
);

-- Insert sample data
INSERT INTO users (name, email, password, type, university, major, industry, bio, skills, location) VALUES
('Amanya Peter', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Makerere University', 'Computer Science', NULL, 'Passionate developer with 3 years experience', 'JavaScript,Python,React', 'Kampala, Uganda'),
('Sarah Mwangi', 'sarah@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'business', NULL, NULL, 'Technology', 'Tech startup focused on digital solutions', 'Leadership,Management,Strategy', 'Nairobi, Kenya'),
('Alex Kiprotich', 'alex@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'University of Nairobi', 'Business Administration', NULL, 'Business student with marketing expertise', 'Marketing,Social Media,Content Writing', 'Nairobi, Kenya'),
('Tech Solutions Inc.', 'info@techsolutions.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'business', NULL, NULL, 'Technology', 'Leading technology consulting firm', 'Software Development,Consulting,IT Services', 'Kampala, Uganda');

INSERT INTO skills (name, category) VALUES
('JavaScript', 'Programming'),
('Python', 'Programming'),
('React', 'Frontend'),
('Node.js', 'Backend'),
('PHP', 'Programming'),
('MySQL', 'Database'),
('HTML/CSS', 'Frontend'),
('Marketing', 'Business'),
('Content Writing', 'Writing'),
('Graphic Design', 'Design'),
('Social Media Management', 'Marketing'),
('Data Analysis', 'Analytics');

INSERT INTO categories (name, description) VALUES
('Web Development', 'Website and web application development'),
('Mobile Development', 'Mobile app development for iOS and Android'),
('Design', 'Graphic design, UI/UX design'),
('Writing', 'Content writing, copywriting, technical writing'),
('Marketing', 'Digital marketing, social media marketing'),
('Data Analysis', 'Data analysis, business intelligence'),
('Consulting', 'Business consulting and advisory services');

INSERT INTO gigs (user_id, title, description, budget, deadline, skills, location, type) VALUES
(2, 'Website Development', 'Need a modern website for our startup. Must be responsive and SEO-friendly.', 500000.00, '2024-12-31', 'HTML/CSS,JavaScript,React', 'Remote', 'one-time'),
(4, 'Social Media Management', 'Manage our social media accounts and create engaging content.', 200000.00, '2024-12-15', 'Social Media Management,Content Writing', 'Remote', 'ongoing'),
(2, 'Mobile App Development', 'Develop a cross-platform mobile app for our business.', 1500000.00, '2025-01-31', 'React Native,JavaScript,Node.js', 'Remote', 'contract'),
(4, 'Data Analysis Project', 'Analyze customer data and provide insights for business growth.', 300000.00, '2024-12-20', 'Data Analysis,Python,Excel', 'On-site', 'one-time');

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type ON users(type);
CREATE INDEX idx_gigs_user_id ON gigs(user_id);
CREATE INDEX idx_gigs_status ON gigs(status);
CREATE INDEX idx_applications_user_id ON applications(user_id);
CREATE INDEX idx_applications_gig_id ON applications(gig_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id);
CREATE INDEX idx_messages_sender_id ON messages(sender_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);

-- Create views for common queries
CREATE VIEW active_gigs AS
SELECT g.*, u.name as business_name, u.industry, u.location as business_location
FROM gigs g
JOIN users u ON g.user_id = u.id
WHERE g.status = 'active';

CREATE VIEW application_details AS
SELECT a.*, g.title as gig_title, g.budget, u1.name as student_name, u2.name as business_name
FROM applications a
JOIN gigs g ON a.gig_id = g.id
JOIN users u1 ON a.user_id = u1.id
JOIN users u2 ON g.user_id = u2.id;

CREATE VIEW conversation_summary AS
SELECT c.*, 
       u1.name as user1_name, 
       u2.name as user2_name,
       (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
       (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
FROM conversations c
JOIN users u1 ON c.user1_id = u1.id
JOIN users u2 ON c.user2_id = u2.id;

