-- IntelliEVal System - Sample Questionnaires Seeding Script
-- This script creates comprehensive sample questionnaires for all sub-categories
-- Run this after the main evaluation_hierarchical_schema.sql

-- =====================================================
-- SAMPLE QUESTIONNAIRES FOR ALL SUB-CATEGORIES
-- =====================================================

-- Standard rating labels for 1-5 scale
SET @rating_labels = '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]';

-- =====================================================
-- STUDENT TO TEACHER EVALUATION QUESTIONNAIRES
-- =====================================================

-- 1. CLASSROOM MANAGEMENT (Sub-category ID: 1)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', @rating_labels, 1, 1, 1),
(1, 'Does the teacher start and end classes on time?', 'rating_1_5', @rating_labels, 1, 2, 1),
(1, 'How organized is the teacher\'s classroom setup?', 'rating_1_5', @rating_labels, 1, 3, 1),
(1, 'Does the teacher handle disruptive behavior effectively?', 'rating_1_5', @rating_labels, 1, 4, 1),
(1, 'How well does the teacher manage classroom activities and transitions?', 'rating_1_5', @rating_labels, 1, 5, 1),
(1, 'Does the teacher create a safe and respectful learning environment?', 'yes_no', NULL, 1, 6, 1),
(1, 'What suggestions do you have for improving classroom management?', 'text', NULL, 0, 7, 1);

-- 2. TEACHING SKILLS (Sub-category ID: 2)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', @rating_labels, 1, 1, 1),
(2, 'Does the teacher use effective teaching methods and strategies?', 'rating_1_5', @rating_labels, 1, 2, 1),
(2, 'How well does the teacher adapt teaching to different learning styles?', 'rating_1_5', @rating_labels, 1, 3, 1),
(2, 'Does the teacher provide clear learning objectives for each lesson?', 'rating_1_5', @rating_labels, 1, 4, 1),
(2, 'How effective are the teacher\'s examples and demonstrations?', 'rating_1_5', @rating_labels, 1, 5, 1),
(2, 'Does the teacher use technology and resources effectively?', 'rating_1_5', @rating_labels, 1, 6, 1),
(2, 'How well does the teacher assess student understanding during lessons?', 'rating_1_5', @rating_labels, 1, 7, 1),
(2, 'What teaching methods do you find most effective?', 'text', NULL, 0, 8, 1);

-- 3. SUBJECT KNOWLEDGE (Sub-category ID: 3)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(3, 'How well does the teacher demonstrate mastery of the subject matter?', 'rating_1_5', @rating_labels, 1, 1, 1),
(3, 'Does the teacher provide accurate and up-to-date information?', 'rating_1_5', @rating_labels, 1, 2, 1),
(3, 'How well does the teacher connect concepts to real-world applications?', 'rating_1_5', @rating_labels, 1, 3, 1),
(3, 'Does the teacher answer student questions accurately and thoroughly?', 'rating_1_5', @rating_labels, 1, 4, 1),
(3, 'How well does the teacher explain complex topics in simple terms?', 'rating_1_5', @rating_labels, 1, 5, 1),
(3, 'Does the teacher stay current with developments in their field?', 'yes_no', NULL, 1, 6, 1),
(3, 'What topics would you like the teacher to explain better?', 'text', NULL, 0, 7, 1);

-- 4. COMMUNICATION SKILLS (Sub-category ID: 4)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(4, 'How clear and audible is the teacher\'s voice?', 'rating_1_5', @rating_labels, 1, 1, 1),
(4, 'Does the teacher speak at an appropriate pace for students to follow?', 'rating_1_5', @rating_labels, 1, 2, 1),
(4, 'How well does the teacher use body language and gestures?', 'rating_1_5', @rating_labels, 1, 3, 1),
(4, 'Does the teacher listen actively to student questions and concerns?', 'rating_1_5', @rating_labels, 1, 4, 1),
(4, 'How well does the teacher provide constructive feedback?', 'rating_1_5', @rating_labels, 1, 5, 1),
(4, 'Does the teacher encourage open communication in the classroom?', 'yes_no', NULL, 1, 6, 1),
(4, 'How approachable is the teacher for questions outside of class?', 'rating_1_5', @rating_labels, 1, 7, 1),
(4, 'What communication improvements would you suggest?', 'text', NULL, 0, 8, 1);

-- 5. STUDENT ENGAGEMENT (Sub-category ID: 5)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(5, 'How well does the teacher motivate students to participate?', 'rating_1_5', @rating_labels, 1, 1, 1),
(5, 'Does the teacher create interesting and engaging lessons?', 'rating_1_5', @rating_labels, 1, 2, 1),
(5, 'How well does the teacher encourage critical thinking and discussion?', 'rating_1_5', @rating_labels, 1, 3, 1),
(5, 'Does the teacher use interactive activities and group work effectively?', 'rating_1_5', @rating_labels, 1, 4, 1),
(5, 'How well does the teacher recognize and respond to student interests?', 'rating_1_5', @rating_labels, 1, 5, 1),
(5, 'Does the teacher provide opportunities for hands-on learning?', 'yes_no', NULL, 1, 6, 1),
(5, 'How well does the teacher maintain student attention throughout the lesson?', 'rating_1_5', @rating_labels, 1, 7, 1),
(5, 'What activities do you find most engaging in this class?', 'text', NULL, 0, 8, 1);

-- =====================================================
-- PEER TO PEER EVALUATION QUESTIONNAIRES
-- =====================================================

-- 6. PROFESSIONAL COMPETENCE (Sub-category ID: 6)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(6, 'How well does the colleague demonstrate expertise in their subject area?', 'rating_1_5', @rating_labels, 1, 1, 1),
(6, 'Does the colleague stay updated with current educational practices?', 'rating_1_5', @rating_labels, 1, 2, 1),
(6, 'How well does the colleague plan and organize their lessons?', 'rating_1_5', @rating_labels, 1, 3, 1),
(6, 'Does the colleague demonstrate strong pedagogical skills?', 'rating_1_5', @rating_labels, 1, 4, 1),
(6, 'How well does the colleague assess and evaluate student learning?', 'rating_1_5', @rating_labels, 1, 5, 1),
(6, 'Does the colleague maintain professional standards in their work?', 'yes_no', NULL, 1, 6, 1),
(6, 'How well does the colleague handle classroom challenges?', 'rating_1_5', @rating_labels, 1, 7, 1),
(6, 'What areas of professional development would you recommend?', 'text', NULL, 0, 8, 1);

-- 7. COLLABORATION (Sub-category ID: 7)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(7, 'How well does the colleague work with other teachers?', 'rating_1_5', @rating_labels, 1, 1, 1),
(7, 'Does the colleague share resources and ideas with the team?', 'rating_1_5', @rating_labels, 1, 2, 1),
(7, 'How well does the colleague participate in team meetings and discussions?', 'rating_1_5', @rating_labels, 1, 3, 1),
(7, 'Does the colleague support and help other teachers when needed?', 'rating_1_5', @rating_labels, 1, 4, 1),
(7, 'How well does the colleague contribute to school-wide initiatives?', 'rating_1_5', @rating_labels, 1, 5, 1),
(7, 'Does the colleague respect different viewpoints and approaches?', 'yes_no', NULL, 1, 6, 1),
(7, 'How well does the colleague communicate with other staff members?', 'rating_1_5', @rating_labels, 1, 7, 1),
(7, 'What suggestions do you have for improving collaboration?', 'text', NULL, 0, 8, 1);

-- 8. INNOVATION (Sub-category ID: 8)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(8, 'How creative and innovative is the colleague in their teaching methods?', 'rating_1_5', @rating_labels, 1, 1, 1),
(8, 'Does the colleague try new approaches and technologies?', 'rating_1_5', @rating_labels, 1, 2, 1),
(8, 'How well does the colleague adapt to changing educational needs?', 'rating_1_5', @rating_labels, 1, 3, 1),
(8, 'Does the colleague suggest improvements to existing programs?', 'rating_1_5', @rating_labels, 1, 4, 1),
(8, 'How well does the colleague integrate new ideas into their teaching?', 'rating_1_5', @rating_labels, 1, 5, 1),
(8, 'Does the colleague experiment with different assessment methods?', 'yes_no', NULL, 1, 6, 1),
(8, 'How well does the colleague inspire creativity in students?', 'rating_1_5', @rating_labels, 1, 7, 1),
(8, 'What innovative practices have you observed from this colleague?', 'text', NULL, 0, 8, 1);

-- 9. MENTORING (Sub-category ID: 9)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(9, 'How well does the colleague mentor new or less experienced teachers?', 'rating_1_5', @rating_labels, 1, 1, 1),
(9, 'Does the colleague provide constructive feedback to other teachers?', 'rating_1_5', @rating_labels, 1, 2, 1),
(9, 'How well does the colleague share their expertise and knowledge?', 'rating_1_5', @rating_labels, 1, 3, 1),
(9, 'Does the colleague serve as a positive role model for other teachers?', 'rating_1_5', @rating_labels, 1, 4, 1),
(9, 'How well does the colleague support professional development of others?', 'rating_1_5', @rating_labels, 1, 5, 1),
(9, 'Does the colleague create opportunities for peer learning?', 'yes_no', NULL, 1, 6, 1),
(9, 'How well does the colleague guide others in improving their teaching?', 'rating_1_5', @rating_labels, 1, 7, 1),
(9, 'What mentoring strengths does this colleague demonstrate?', 'text', NULL, 0, 8, 1);

-- =====================================================
-- HEAD TO TEACHER EVALUATION QUESTIONNAIRES
-- =====================================================

-- 10. LEADERSHIP (Sub-category ID: 10)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(10, 'How well does the teacher demonstrate leadership qualities?', 'rating_1_5', @rating_labels, 1, 1, 1),
(10, 'Does the teacher take initiative in school improvement projects?', 'rating_1_5', @rating_labels, 1, 2, 1),
(10, 'How well does the teacher inspire and motivate other staff members?', 'rating_1_5', @rating_labels, 1, 3, 1),
(10, 'Does the teacher demonstrate vision and strategic thinking?', 'rating_1_5', @rating_labels, 1, 4, 1),
(10, 'How well does the teacher handle conflicts and difficult situations?', 'rating_1_5', @rating_labels, 1, 5, 1),
(10, 'Does the teacher lead by example in professional conduct?', 'yes_no', NULL, 1, 6, 1),
(10, 'How well does the teacher represent the school in external activities?', 'rating_1_5', @rating_labels, 1, 7, 1),
(10, 'What leadership opportunities would you recommend for this teacher?', 'text', NULL, 0, 8, 1);

-- 11. ADMINISTRATIVE SKILLS (Sub-category ID: 11)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(11, 'How well does the teacher manage administrative tasks and paperwork?', 'rating_1_5', @rating_labels, 1, 1, 1),
(11, 'Does the teacher submit reports and documents on time?', 'rating_1_5', @rating_labels, 1, 2, 1),
(11, 'How well does the teacher organize and maintain records?', 'rating_1_5', @rating_labels, 1, 3, 1),
(11, 'Does the teacher follow administrative procedures correctly?', 'rating_1_5', @rating_labels, 1, 4, 1),
(11, 'How well does the teacher manage time and prioritize tasks?', 'rating_1_5', @rating_labels, 1, 5, 1),
(11, 'Does the teacher coordinate effectively with other departments?', 'yes_no', NULL, 1, 6, 1),
(11, 'How well does the teacher handle budget and resource management?', 'rating_1_5', @rating_labels, 1, 7, 1),
(11, 'What administrative improvements would you suggest?', 'text', NULL, 0, 8, 1);

-- 12. PROFESSIONAL DEVELOPMENT (Sub-category ID: 12)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(12, 'How committed is the teacher to continuous professional learning?', 'rating_1_5', @rating_labels, 1, 1, 1),
(12, 'Does the teacher actively participate in training and workshops?', 'rating_1_5', @rating_labels, 1, 2, 1),
(12, 'How well does the teacher apply new learning to their practice?', 'rating_1_5', @rating_labels, 1, 3, 1),
(12, 'Does the teacher seek feedback and reflect on their teaching?', 'rating_1_5', @rating_labels, 1, 4, 1),
(12, 'How well does the teacher stay current with educational trends?', 'rating_1_5', @rating_labels, 1, 5, 1),
(12, 'Does the teacher pursue advanced degrees or certifications?', 'yes_no', NULL, 1, 6, 1),
(12, 'How well does the teacher share new knowledge with colleagues?', 'rating_1_5', @rating_labels, 1, 7, 1),
(12, 'What professional development goals would you recommend?', 'text', NULL, 0, 8, 1);

-- 13. COMPLIANCE (Sub-category ID: 13)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(13, 'How well does the teacher follow school policies and procedures?', 'rating_1_5', @rating_labels, 1, 1, 1),
(13, 'Does the teacher comply with curriculum standards and requirements?', 'rating_1_5', @rating_labels, 1, 2, 1),
(13, 'How well does the teacher adhere to safety and security protocols?', 'rating_1_5', @rating_labels, 1, 3, 1),
(13, 'Does the teacher follow ethical guidelines and professional standards?', 'rating_1_5', @rating_labels, 1, 4, 1),
(13, 'How well does the teacher maintain confidentiality when required?', 'rating_1_5', @rating_labels, 1, 5, 1),
(13, 'Does the teacher attend required meetings and events?', 'yes_no', NULL, 1, 6, 1),
(13, 'How well does the teacher follow assessment and grading policies?', 'rating_1_5', @rating_labels, 1, 7, 1),
(13, 'What compliance issues, if any, need to be addressed?', 'text', NULL, 0, 8, 1);

-- =====================================================
-- SUMMARY
-- =====================================================

-- This script creates:
-- - 7 questions for Classroom Management (Student to Teacher)
-- - 8 questions for Teaching Skills (Student to Teacher)
-- - 7 questions for Subject Knowledge (Student to Teacher)
-- - 8 questions for Communication Skills (Student to Teacher)
-- - 8 questions for Student Engagement (Student to Teacher)
-- - 8 questions for Professional Competence (Peer to Peer)
-- - 8 questions for Collaboration (Peer to Peer)
-- - 8 questions for Innovation (Peer to Peer)
-- - 8 questions for Mentoring (Peer to Peer)
-- - 8 questions for Leadership (Head to Teacher)
-- - 8 questions for Administrative Skills (Head to Teacher)
-- - 8 questions for Professional Development (Head to Teacher)
-- - 8 questions for Compliance (Head to Teacher)
-- 
-- Total: 100 sample questions across all 13 sub-categories
-- 
-- Question types used:
-- - rating_1_5: Standard 1-5 rating scale with labels
-- - yes_no: Binary choice questions
-- - text: Open-ended text responses 