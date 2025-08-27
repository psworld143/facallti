-- Syllabus Seed Data (Final Corrected Version)
-- This script provides sample data for the syllabus system with alignment features

-- First, ensure the references_field column exists in syllabus_topics
ALTER TABLE `syllabus_topics` 
ADD COLUMN IF NOT EXISTS `references_field` text AFTER `materials`;

-- Sample Class Syllabus Data (only if not exists)
INSERT IGNORE INTO `class_syllabus` (`id`, `class_id`, `teacher_id`, `title`, `description`, `course_objectives`, `learning_outcomes`, `prerequisites`, `course_requirements`, `grading_system`, `course_policies`, `academic_integrity`, `attendance_policy`, `late_submission_policy`, `office_hours`, `contact_information`, `textbooks`, `course_references`, `schedule`, `assessment_methods`, `course_units`, `course_credits`, `semester`, `academic_year`, `class_schedule`, `classroom_location`, `course_website`, `emergency_contact`, `disability_accommodations`, `is_published`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Introduction to Computer Science', 'This course provides a comprehensive introduction to computer science fundamentals, programming concepts, and problem-solving techniques.', 'By the end of this course, students will be able to:\n• Understand fundamental computer science concepts\n• Develop basic programming skills\n• Apply problem-solving methodologies\n• Analyze algorithms and data structures', 'Upon completion of this course, students will be able to:\n• Write and debug simple programs\n• Understand basic data structures\n• Apply computational thinking\n• Work with basic algorithms', 'High school mathematics, basic computer literacy', 'Laptop computer, programming software, internet access', 'Quizzes: 20%\nAssignments: 30%\nMidterm Exam: 25%\nFinal Project: 25%', 'Regular attendance required, participation encouraged, academic integrity expected', 'All work must be original. Plagiarism will result in course failure.', 'Attendance is mandatory. More than 3 absences may affect grade.', 'Late assignments accepted with 10% penalty per day up to 3 days.', 'Monday and Wednesday 2:00-3:00 PM, Room 201', 'Email: professor@university.edu\nPhone: (555) 123-4567\nOffice: Room 201, Computer Science Building', 'Starting Out with Python (5th Edition) by Tony Gaddis', 'Additional online resources and documentation will be provided', 'Week 1-2: Introduction to Programming\nWeek 3-4: Variables and Data Types\nWeek 5-6: Control Structures\nWeek 7-8: Functions\nWeek 9-10: Arrays and Lists\nWeek 11-12: Object-Oriented Programming\nWeek 13-14: File Handling\nWeek 15-16: Final Project', 'Programming assignments, quizzes, exams, and a final project', '3', '3', 'First Semester', '2024-2025', 'Monday and Wednesday 9:00-10:30 AM', 'Room 101, Computer Science Building', 'https://canvas.university.edu/courses/12345', 'For emergencies during class, contact campus security at (555) 999-1111', 'Students with disabilities should contact the Office of Disability Services for accommodations.', 1, '2024-08-15 10:00:00', '2024-08-15 10:00:00', '2024-08-15 10:00:00');

-- Sample Program Educational Objectives (PEOs)
INSERT IGNORE INTO `syllabus_peos` (`id`, `syllabus_id`, `peo_code`, `peo_description`, `aligned_to_mission`, `order_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'PEO1', 'Graduates will demonstrate technical competence in computer science and related fields, applying their knowledge to solve real-world problems effectively.', 1, 1, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(2, 1, 'PEO2', 'Graduates will exhibit strong communication skills, both written and oral, enabling them to collaborate effectively in diverse professional environments.', 1, 2, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(3, 1, 'PEO3', 'Graduates will demonstrate ethical responsibility and professional integrity in their work, contributing positively to society and the computing profession.', 1, 3, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(4, 1, 'PEO4', 'Graduates will engage in lifelong learning and professional development, adapting to evolving technologies and industry demands.', 0, 4, '2024-08-15 10:00:00', '2024-08-15 10:00:00');

-- Sample Program Outcomes (POs)
INSERT IGNORE INTO `syllabus_pos` (`id`, `syllabus_id`, `po_code`, `po_description`, `order_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'PO1', 'Apply knowledge of mathematics, science, and engineering fundamentals to solve complex engineering problems.', 1, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(2, 1, 'PO2', 'Identify, formulate, and solve complex engineering problems using appropriate engineering tools and techniques.', 2, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(3, 1, 'PO3', 'Design solutions for complex engineering problems and design system components or processes that meet specified needs.', 3, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(4, 1, 'PO4', 'Conduct investigations of complex problems using research-based knowledge and research methods.', 4, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(5, 1, 'PO5', 'Create, select, and apply appropriate techniques, resources, and modern engineering and IT tools.', 5, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(6, 1, 'PO6', 'Apply reasoning informed by contextual knowledge to assess societal, health, safety, legal, and cultural issues.', 6, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(7, 1, 'PO7', 'Understand the impact of professional engineering solutions in societal and environmental contexts.', 7, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(8, 1, 'PO8', 'Apply ethical principles and commit to professional ethics and responsibilities and norms of engineering practice.', 8, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(9, 1, 'PO9', 'Function effectively as an individual, and as a member or leader in diverse teams and in multidisciplinary settings.', 9, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(10, 1, 'PO10', 'Communicate effectively on complex engineering activities with the engineering community and society at large.', 10, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(11, 1, 'PO11', 'Demonstrate knowledge and understanding of engineering and management principles.', 11, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(12, 1, 'PO12', 'Recognize the need for and have the preparation and ability to engage in independent and life-long learning.', 12, '2024-08-15 10:00:00', '2024-08-15 10:00:00');

-- Sample Course Learning Outcomes (CLOs)
INSERT IGNORE INTO `syllabus_clos` (`id`, `syllabus_id`, `clo_code`, `clo_description`, `order_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'CLO1', 'Demonstrate understanding of fundamental programming concepts and syntax.', 1, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(2, 1, 'CLO2', 'Apply problem-solving techniques to develop algorithmic solutions.', 2, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(3, 1, 'CLO3', 'Implement and debug programs using appropriate programming constructs.', 3, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(4, 1, 'CLO4', 'Analyze and compare different data structures and their applications.', 4, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(5, 1, 'CLO5', 'Design and implement object-oriented programs with proper encapsulation and inheritance.', 5, '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(6, 1, 'CLO6', 'Apply software engineering principles in program development and documentation.', 6, '2024-08-15 10:00:00', '2024-08-15 10:00:00');

-- Sample PEO-PO Alignments
INSERT IGNORE INTO `syllabus_peo_po_alignment` (`id`, `syllabus_id`, `peo_id`, `po_id`, `is_aligned`, `created_at`) VALUES
(1, 1, 1, 1, 1, '2024-08-15 10:00:00'),
(2, 1, 1, 2, 1, '2024-08-15 10:00:00'),
(3, 1, 1, 3, 1, '2024-08-15 10:00:00'),
(4, 1, 1, 5, 1, '2024-08-15 10:00:00'),
(5, 1, 2, 9, 1, '2024-08-15 10:00:00'),
(6, 1, 2, 10, 1, '2024-08-15 10:00:00'),
(7, 1, 3, 6, 1, '2024-08-15 10:00:00'),
(8, 1, 3, 7, 1, '2024-08-15 10:00:00'),
(9, 1, 3, 8, 1, '2024-08-15 10:00:00'),
(10, 1, 4, 4, 1, '2024-08-15 10:00:00'),
(11, 1, 4, 11, 1, '2024-08-15 10:00:00'),
(12, 1, 4, 12, 1, '2024-08-15 10:00:00');

-- Sample CLO-PO Alignments
INSERT IGNORE INTO `syllabus_clo_po_alignment` (`id`, `syllabus_id`, `clo_id`, `po_id`, `is_aligned`, `created_at`) VALUES
(1, 1, 1, 1, 1, '2024-08-15 10:00:00'),
(2, 1, 1, 5, 1, '2024-08-15 10:00:00'),
(3, 1, 2, 2, 1, '2024-08-15 10:00:00'),
(4, 1, 2, 3, 1, '2024-08-15 10:00:00'),
(5, 1, 3, 1, 1, '2024-08-15 10:00:00'),
(6, 1, 3, 5, 1, '2024-08-15 10:00:00'),
(7, 1, 4, 2, 1, '2024-08-15 10:00:00'),
(8, 1, 4, 3, 1, '2024-08-15 10:00:00'),
(9, 1, 5, 3, 1, '2024-08-15 10:00:00'),
(10, 1, 5, 5, 1, '2024-08-15 10:00:00'),
(11, 1, 6, 8, 1, '2024-08-15 10:00:00'),
(12, 1, 6, 10, 1, '2024-08-15 10:00:00');

-- Sample Syllabus Topics with Enhanced Fields
INSERT IGNORE INTO `syllabus_topics` (`id`, `syllabus_id`, `week_number`, `order_number`, `topic_title`, `description`, `learning_objectives`, `materials`, `references_field`, `activities`, `assessment`, `values_integration`, `target`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'Introduction to Programming Concepts', 'Overview of computer programming, algorithms, and problem-solving approaches. Introduction to the Python programming environment.', '• Understand what programming is and its applications\n• Learn about algorithms and problem-solving\n• Set up the Python development environment\n• Write and run a simple "Hello World" program', 'Python IDE (PyCharm or VS Code), Python 3.x, course textbook', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Documentation: https://docs.python.org/3/\nOnline Python Tutorial: https://www.w3schools.com/python/', 'Interactive lecture, hands-on coding exercises, group discussions', 'Quiz on programming concepts (10 points)\nSimple program submission (15 points)', 'Ethical considerations in programming and software development. Understanding the impact of technology on society.', 'Students will be able to explain basic programming concepts and write simple Python programs.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(2, 1, 2, 2, 'Variables and Data Types', 'Understanding variables, constants, and different data types in Python including integers, floats, strings, and booleans.', '• Define and use variables in Python\n• Understand different data types\n• Perform type conversion\n• Use input/output functions', 'Python IDE, course textbook, online resources', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Data Types: https://docs.python.org/3/library/stdtypes.html', 'Hands-on coding exercises, type conversion practice, input/output programming', 'Programming assignment: Calculator program (20 points)\nData type quiz (10 points)', 'Accuracy and precision in data handling. Understanding the importance of correct data representation.', 'Students will demonstrate proficiency in using variables and data types to create functional programs.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(3, 1, 3, 3, 'Control Structures: Decision Making', 'Introduction to conditional statements, if-else structures, and logical operators for program flow control.', '• Write conditional statements using if, elif, and else\n• Use comparison and logical operators\n• Implement decision-making logic\n• Debug conditional statements', 'Python IDE, course textbook, practice problems', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Control Flow: https://docs.python.org/3/tutorial/controlflow.html', 'Problem-solving exercises, decision tree creation, debugging practice', 'Programming assignment: Grade calculator (25 points)\nControl structure quiz (15 points)', 'Fairness and objectivity in decision-making algorithms. Understanding bias in automated systems.', 'Students will create programs that make decisions based on input conditions.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(4, 1, 4, 4, 'Control Structures: Loops', 'Understanding and implementing different types of loops including for loops, while loops, and nested loops.', '• Implement for loops and while loops\n• Use loop control statements (break, continue)\n• Create nested loop structures\n• Optimize loop performance', 'Python IDE, course textbook, loop practice problems', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Loops: https://docs.python.org/3/tutorial/controlflow.html#for-statements', 'Loop pattern recognition, nested loop exercises, performance analysis', 'Programming assignment: Number pattern generator (30 points)\nLoop efficiency quiz (10 points)', 'Efficiency and optimization in programming. Understanding the impact of algorithm choices on performance.', 'Students will demonstrate mastery of loop structures and create efficient iterative solutions.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(5, 1, 5, 5, 'Functions and Modular Programming', 'Creating and using functions, understanding parameters, return values, and the concept of modular programming.', '• Define and call functions\n• Use parameters and return values\n• Understand scope and lifetime of variables\n• Apply modular programming principles', 'Python IDE, course textbook, function examples', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Functions: https://docs.python.org/3/tutorial/controlflow.html#defining-functions', 'Function design exercises, modular program development, code organization practice', 'Programming assignment: Library management system (35 points)\nFunction design quiz (15 points)', 'Collaboration and code sharing. Understanding the importance of reusable and maintainable code.', 'Students will create modular programs using functions and demonstrate code reusability.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(6, 1, 6, 6, 'Lists and Arrays', 'Working with lists, list operations, list comprehensions, and basic array concepts in Python.', '• Create and manipulate lists\n• Use list methods and operations\n• Implement list comprehensions\n• Apply lists in problem-solving', 'Python IDE, course textbook, list manipulation examples', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Lists: https://docs.python.org/3/tutorial/datastructures.html', 'List manipulation exercises, data processing tasks, list comprehension practice', 'Programming assignment: Student grade tracker (40 points)\nList operations quiz (15 points)', 'Data organization and management. Understanding the importance of structured data storage.', 'Students will efficiently manage and process data using list structures.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(7, 1, 7, 7, 'Object-Oriented Programming Basics', 'Introduction to classes, objects, attributes, methods, and basic OOP concepts in Python.', '• Define classes and create objects\n• Use attributes and methods\n• Understand encapsulation\n• Apply OOP principles', 'Python IDE, course textbook, OOP examples', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython Classes: https://docs.python.org/3/tutorial/classes.html', 'Class design exercises, object creation practice, encapsulation examples', 'Programming assignment: Bank account system (45 points)\nOOP concepts quiz (20 points)', 'Object-oriented thinking and abstraction. Understanding how to model real-world entities.', 'Students will design and implement object-oriented solutions to programming problems.', '2024-08-15 10:00:00', '2024-08-15 10:00:00'),
(8, 1, 8, 8, 'File Handling and Data Persistence', 'Reading from and writing to files, understanding file operations, and data persistence concepts.', '• Open, read, and write files\n• Handle file exceptions\n• Process different file formats\n• Implement data persistence', 'Python IDE, course textbook, sample data files', 'Gaddis, T. (2020). Starting Out with Python (5th ed.). Pearson.\nPython File I/O: https://docs.python.org/3/tutorial/inputoutput.html', 'File processing exercises, data analysis tasks, error handling practice', 'Programming assignment: Contact management system (50 points)\nFile operations quiz (15 points)', 'Data security and privacy. Understanding the responsibility of handling user data.', 'Students will create programs that can persist and retrieve data from files.', '2024-08-15 10:00:00', '2024-08-15 10:00:00');

-- Sample Topic-CLO Alignments
INSERT IGNORE INTO `syllabus_topic_clo_alignment` (`id`, `syllabus_id`, `topic_id`, `clo_id`, `is_aligned`, `created_at`) VALUES
(1, 1, 1, 1, 1, '2024-08-15 10:00:00'),
(2, 1, 1, 2, 1, '2024-08-15 10:00:00'),
(3, 1, 2, 1, 1, '2024-08-15 10:00:00'),
(4, 1, 2, 3, 1, '2024-08-15 10:00:00'),
(5, 1, 3, 2, 1, '2024-08-15 10:00:00'),
(6, 1, 3, 3, 1, '2024-08-15 10:00:00'),
(7, 1, 4, 2, 1, '2024-08-15 10:00:00'),
(8, 1, 4, 3, 1, '2024-08-15 10:00:00'),
(9, 1, 5, 3, 1, '2024-08-15 10:00:00'),
(10, 1, 5, 6, 1, '2024-08-15 10:00:00'),
(11, 1, 6, 4, 1, '2024-08-15 10:00:00'),
(12, 1, 6, 3, 1, '2024-08-15 10:00:00'),
(13, 1, 7, 5, 1, '2024-08-15 10:00:00'),
(14, 1, 7, 6, 1, '2024-08-15 10:00:00'),
(15, 1, 8, 3, 1, '2024-08-15 10:00:00'),
(16, 1, 8, 6, 1, '2024-08-15 10:00:00');

-- Sample Syllabus Files
INSERT IGNORE INTO `syllabus_files` (`id`, `syllabus_id`, `file_name`, `file_path`, `file_type`, `file_size`, `description`, `uploaded_at`) VALUES
(1, 1, 'Course_Schedule_2024.pdf', 'syllabus/course_schedule_2024.pdf', 'application/pdf', 245760, 'Detailed weekly schedule with assignments and deadlines', '2024-08-15 10:00:00'),
(2, 1, 'Programming_Exercises.pdf', 'syllabus/programming_exercises.pdf', 'application/pdf', 512000, 'Collection of practice problems and exercises', '2024-08-15 10:00:00'),
(3, 1, 'Python_Cheat_Sheet.pdf', 'syllabus/python_cheat_sheet.pdf', 'application/pdf', 102400, 'Quick reference guide for Python syntax and functions', '2024-08-15 10:00:00');
