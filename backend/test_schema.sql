-- ═══════════════════════════════════════════════════════════════════════════
-- StudyPulse - Online Test System Schema
-- Run this file AFTER database.sql to add test system tables
-- ═══════════════════════════════════════════════════════════════════════════

USE studypulse;

-- Tests table
CREATE TABLE IF NOT EXISTS tests (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    total_questions INT NOT NULL DEFAULT 45,
    total_marks INT NOT NULL DEFAULT 45,
    duration_minutes INT NOT NULL DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Questions table
CREATE TABLE IF NOT EXISTS questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option CHAR(1) NOT NULL,
    difficulty_level VARCHAR(20) NOT NULL DEFAULT 'MEDIUM',
    FOREIGN KEY (test_id) REFERENCES tests(test_id) ON DELETE CASCADE,
    INDEX idx_test_id (test_id),
    INDEX idx_difficulty (difficulty_level),
    CONSTRAINT chk_correct_option CHECK (correct_option IN ('A', 'B', 'C', 'D')),
    CONSTRAINT chk_difficulty CHECK (difficulty_level IN ('EASY', 'MEDIUM', 'HARD'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Test Attempts table
CREATE TABLE IF NOT EXISTS test_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    test_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    status ENUM('IN_PROGRESS', 'SUBMITTED', 'AUTO_SUBMITTED') NOT NULL DEFAULT 'IN_PROGRESS',
    score INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(test_id) ON DELETE CASCADE,
    INDEX idx_user_test (user_id, test_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Answers table
CREATE TABLE IF NOT EXISTS user_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    is_correct TINYINT(1) DEFAULT NULL,
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY uq_attempt_question (attempt_id, question_id),
    INDEX idx_attempt_id (attempt_id),
    CONSTRAINT chk_selected_option CHECK (selected_option IN ('A', 'B', 'C', 'D'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════════
-- SEED DATA: Sample Test with 45 Questions
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO tests (title, total_questions, total_marks, duration_minutes)
VALUES ('General Knowledge & Computer Science', 45, 45, 60);

SET @test_id = LAST_INSERT_ID();

INSERT INTO questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty_level) VALUES
(@test_id, 'What does CPU stand for?', 'Central Processing Unit', 'Central Program Utility', 'Computer Personal Unit', 'Central Processor Unifier', 'A', 'EASY'),
(@test_id, 'Which data structure uses FIFO?', 'Stack', 'Queue', 'Tree', 'Graph', 'B', 'EASY'),
(@test_id, 'What is the time complexity of binary search?', 'O(n)', 'O(n^2)', 'O(log n)', 'O(1)', 'C', 'MEDIUM'),
(@test_id, 'Which language is used for web page structure?', 'Python', 'Java', 'HTML', 'C++', 'C', 'EASY'),
(@test_id, 'What does SQL stand for?', 'Structured Query Language', 'Simple Query Language', 'Standard Query Logic', 'Sequential Query Language', 'A', 'EASY'),
(@test_id, 'Which sorting algorithm has the best average-case time complexity?', 'Bubble Sort', 'Selection Sort', 'Merge Sort', 'Insertion Sort', 'C', 'MEDIUM'),
(@test_id, 'What is the default port for HTTP?', '443', '21', '80', '8080', 'C', 'EASY'),
(@test_id, 'Which of the following is NOT a programming language?', 'Java', 'Python', 'HTML', 'C#', 'C', 'EASY'),
(@test_id, 'What does OOP stand for?', 'Object Oriented Programming', 'Optimal Operation Processing', 'Open Object Protocol', 'Ordered Output Programming', 'A', 'EASY'),
(@test_id, 'Which data structure uses LIFO?', 'Queue', 'Array', 'Stack', 'Linked List', 'C', 'EASY'),
(@test_id, 'What is the worst-case time complexity of Quick Sort?', 'O(n log n)', 'O(n)', 'O(n^2)', 'O(log n)', 'C', 'MEDIUM'),
(@test_id, 'Which protocol is used for secure web communication?', 'HTTP', 'FTP', 'HTTPS', 'SMTP', 'C', 'EASY'),
(@test_id, 'What is a foreign key in a database?', 'A key from another table', 'The primary key', 'An encrypted key', 'A temporary key', 'A', 'MEDIUM'),
(@test_id, 'Which of these is a NoSQL database?', 'MySQL', 'PostgreSQL', 'MongoDB', 'Oracle', 'C', 'MEDIUM'),
(@test_id, 'What does API stand for?', 'Application Programming Interface', 'Applied Program Integration', 'Automatic Protocol Interface', 'Application Process Integration', 'A', 'EASY'),
(@test_id, 'What is the result of 2^10?', '512', '1024', '2048', '256', 'B', 'EASY'),
(@test_id, 'Which layer of the OSI model handles routing?', 'Data Link Layer', 'Transport Layer', 'Network Layer', 'Session Layer', 'C', 'MEDIUM'),
(@test_id, 'What is polymorphism in OOP?', 'Multiple inheritance', 'Method with same name different behavior', 'Data hiding', 'Code reuse', 'B', 'MEDIUM'),
(@test_id, 'Which of these is NOT a valid HTTP method?', 'GET', 'POST', 'FETCH', 'DELETE', 'C', 'MEDIUM'),
(@test_id, 'What does DNS stand for?', 'Dynamic Network Service', 'Domain Name System', 'Data Network Standard', 'Digital Naming Service', 'B', 'EASY'),
(@test_id, 'Which algorithm is used for shortest path in a weighted graph?', 'BFS', 'DFS', 'Dijkstra', 'Prim', 'C', 'HARD'),
(@test_id, 'What is the space complexity of merge sort?', 'O(1)', 'O(n)', 'O(log n)', 'O(n^2)', 'B', 'HARD'),
(@test_id, 'Which design pattern ensures only one instance of a class?', 'Factory', 'Observer', 'Singleton', 'Decorator', 'C', 'MEDIUM'),
(@test_id, 'What is encapsulation in OOP?', 'Inheriting properties', 'Bundling data and methods together', 'Method overloading', 'Multiple interfaces', 'B', 'MEDIUM'),
(@test_id, 'Which of these is a version control system?', 'Docker', 'Kubernetes', 'Git', 'Jenkins', 'C', 'EASY'),
(@test_id, 'What is the maximum number of children a binary tree node can have?', '1', '2', '3', 'Unlimited', 'B', 'EASY'),
(@test_id, 'Which CSS property is used to change text color?', 'font-color', 'text-color', 'color', 'foreground-color', 'C', 'EASY'),
(@test_id, 'What is a deadlock in operating systems?', 'System crash', 'Circular wait condition', 'Memory overflow', 'CPU overload', 'B', 'HARD'),
(@test_id, 'Which JavaScript method converts a JSON string to an object?', 'JSON.stringify()', 'JSON.parse()', 'JSON.convert()', 'JSON.decode()', 'B', 'MEDIUM'),
(@test_id, 'What is the primary purpose of an index in a database?', 'Data encryption', 'Faster query execution', 'Data backup', 'Schema validation', 'B', 'MEDIUM'),
(@test_id, 'Which of these is a valid IP address?', '192.168.1.256', '10.0.0.1', '300.1.1.1', '192.168.1.1.1', 'B', 'MEDIUM'),
(@test_id, 'What is recursion?', 'Looping through arrays', 'A function calling itself', 'Variable declaration', 'Error handling', 'B', 'MEDIUM'),
(@test_id, 'Which data structure is used in BFS?', 'Stack', 'Queue', 'Heap', 'Array', 'B', 'MEDIUM'),
(@test_id, 'What does REST stand for?', 'Representational State Transfer', 'Remote Execution Standard Technology', 'Resource State Transition', 'Real-time Exchange System Transfer', 'A', 'MEDIUM'),
(@test_id, 'Which of these is NOT a pillar of OOP?', 'Abstraction', 'Encapsulation', 'Compilation', 'Inheritance', 'C', 'EASY'),
(@test_id, 'What is the time complexity of accessing an element in an array by index?', 'O(n)', 'O(log n)', 'O(1)', 'O(n^2)', 'C', 'EASY'),
(@test_id, 'Which type of join returns all rows from both tables?', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL OUTER JOIN', 'D', 'MEDIUM'),
(@test_id, 'What is a hash table?', 'A table with sequential data', 'Key-value pair data structure', 'A type of tree', 'A sorting algorithm', 'B', 'MEDIUM'),
(@test_id, 'Which of these is used for containerization?', 'Git', 'Docker', 'Maven', 'Gradle', 'B', 'MEDIUM'),
(@test_id, 'What is the purpose of the finally block in exception handling?', 'Execute only on error', 'Execute only on success', 'Always execute', 'Skip execution', 'C', 'MEDIUM'),
(@test_id, 'Which traversal visits the root node first?', 'In-order', 'Pre-order', 'Post-order', 'Level-order', 'B', 'MEDIUM'),
(@test_id, 'What is normalization in databases?', 'Data encryption process', 'Reducing data redundancy', 'Increasing storage', 'Backing up data', 'B', 'HARD'),
(@test_id, 'Which of these is a functional programming language?', 'Java', 'C++', 'Haskell', 'PHP', 'C', 'HARD'),
(@test_id, 'What is the purpose of a load balancer?', 'Encrypt data', 'Distribute network traffic', 'Store session data', 'Compile code', 'B', 'HARD'),
(@test_id, 'Which of these is an example of a greedy algorithm?', 'Merge Sort', 'Binary Search', 'Huffman Coding', 'BFS', 'C', 'HARD');
