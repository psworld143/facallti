-- IntelliEVal System - Additional Text Type Questionnaires Seeding Script
-- This script adds comprehensive text type questions for each evaluation sub-category
-- Specifically for Student to Teacher evaluations
-- Run this after the main seed_questionnaires.sql

-- =====================================================
-- ADDITIONAL TEXT TYPE QUESTIONNAIRES FOR STUDENT TO TEACHER EVALUATION
-- =====================================================

-- 1. CLASSROOM MANAGEMENT (Sub-category ID: 1) - Additional Text Questions
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(1, 'Describe a specific situation where the teacher handled classroom discipline effectively:', 'text', NULL, 0, 8, 1),
(1, 'What specific classroom management strategies have you observed from this teacher?', 'text', NULL, 0, 9, 1),
(1, 'How does the teacher create a positive learning environment? Please provide examples:', 'text', NULL, 0, 10, 1),
(1, 'What improvements would you suggest for the teacher\'s classroom management approach?', 'text', NULL, 0, 11, 1),
(1, 'Describe how the teacher handles transitions between different activities:', 'text', NULL, 0, 12, 1);

-- 2. TEACHING SKILLS (Sub-category ID: 2) - Additional Text Questions
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(2, 'Describe a lesson where the teacher\'s explanation was particularly clear and helpful:', 'text', NULL, 0, 9, 1),
(2, 'What specific teaching methods or strategies do you find most effective in this class?', 'text', NULL, 0, 10, 1),
(2, 'How does the teacher adapt their teaching to accommodate different learning styles?', 'text', NULL, 0, 11, 1),
(2, 'Describe an example of how the teacher uses technology to enhance learning:', 'text', NULL, 0, 12, 1),
(2, 'What suggestions do you have for improving the teacher\'s instructional methods?', 'text', NULL, 0, 13, 1),
(2, 'How does the teacher check for student understanding during lessons?', 'text', NULL, 0, 14, 1);

-- 3. SUBJECT KNOWLEDGE (Sub-category ID: 3) - Additional Text Questions
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(3, 'Describe a time when the teacher demonstrated deep knowledge of the subject matter:', 'text', NULL, 0, 8, 1),
(3, 'How does the teacher connect classroom concepts to real-world applications?', 'text', NULL, 0, 9, 1),
(3, 'What specific examples or analogies does the teacher use to explain complex topics?', 'text', NULL, 0, 10, 1),
(3, 'Describe how the teacher answers difficult questions from students:', 'text', NULL, 0, 11, 1),
(3, 'What areas of the subject would you like the teacher to explain in more detail?', 'text', NULL, 0, 12, 1),
(3, 'How does the teacher stay current with developments in their field?', 'text', NULL, 0, 13, 1);

-- 4. COMMUNICATION SKILLS (Sub-category ID: 4) - Additional Text Questions
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(4, 'Describe how the teacher communicates complex ideas in simple terms:', 'text', NULL, 0, 9, 1),
(4, 'What specific communication techniques does the teacher use effectively?', 'text', NULL, 0, 10, 1),
(4, 'How does the teacher encourage students to ask questions and participate?', 'text', NULL, 0, 11, 1),
(4, 'Describe how the teacher provides feedback on student work:', 'text', NULL, 0, 12, 1),
(4, 'What communication barriers, if any, have you observed in this class?', 'text', NULL, 0, 13, 1),
(4, 'How does the teacher handle communication with students outside of class time?', 'text', NULL, 0, 14, 1),
(4, 'What specific improvements would you suggest for the teacher\'s communication style?', 'text', NULL, 0, 15, 1);

-- 5. STUDENT ENGAGEMENT (Sub-category ID: 5) - Additional Text Questions
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(5, 'Describe a specific activity or lesson that was particularly engaging:', 'text', NULL, 0, 9, 1),
(5, 'How does the teacher motivate students who seem disinterested?', 'text', NULL, 0, 10, 1),
(5, 'What types of interactive activities does the teacher use most effectively?', 'text', NULL, 0, 11, 1),
(5, 'Describe how the teacher encourages critical thinking and discussion:', 'text', NULL, 0, 12, 1),
(5, 'What specific strategies does the teacher use to maintain student attention?', 'text', NULL, 0, 13, 1),
(5, 'How does the teacher recognize and respond to student interests and questions?', 'text', NULL, 0, 14, 1),
(5, 'What additional activities or approaches would make this class more engaging?', 'text', NULL, 0, 15, 1);

-- =====================================================
-- SUMMARY OF ADDED TEXT QUESTIONS
-- =====================================================

-- This script adds the following text type questions:

-- Classroom Management (Sub-category 1): 5 additional text questions
-- - Total: 12 questions (7 original + 5 new text questions)

-- Teaching Skills (Sub-category 2): 6 additional text questions  
-- - Total: 14 questions (8 original + 6 new text questions)

-- Subject Knowledge (Sub-category 3): 6 additional text questions
-- - Total: 13 questions (7 original + 6 new text questions)

-- Communication Skills (Sub-category 4): 7 additional text questions
-- - Total: 15 questions (8 original + 7 new text questions)

-- Student Engagement (Sub-category 5): 7 additional text questions
-- - Total: 15 questions (8 original + 7 new text questions)

-- Total additional text questions added: 31
-- Total questions per sub-category after this script:
-- - Classroom Management: 12 questions
-- - Teaching Skills: 14 questions  
-- - Subject Knowledge: 13 questions
-- - Communication Skills: 15 questions
-- - Student Engagement: 15 questions

-- All text questions are marked as not required (required = 0) to allow for optional responses
-- Order numbers continue from the existing questions to maintain proper sequencing 