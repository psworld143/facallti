<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - SEAIT</title>
    <link rel="icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <script src="assets/js/dark-mode.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode min-h-screen" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-seait-dark mb-6">Privacy Policy</h1>

            <div class="prose max-w-none">
                <p class="text-gray-600 mb-6">
                    <strong>Last updated:</strong> December 2024
                </p>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">1. Introduction</h2>
                    <p class="text-gray-700 mb-4">
                        South East Asian Institute of Technology, Inc. (SEAIT) is committed to protecting your privacy.
                        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when
                        you visit our website.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">2. Information We Collect</h2>

                    <h3 class="text-xl font-semibold text-seait-dark mb-3">2.1 Personal Information</h3>
                    <p class="text-gray-700 mb-4">
                        We may collect personal information that you voluntarily provide to us when you:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 mb-4 space-y-2">
                        <li>Contact us through our website forms</li>
                        <li>Apply for admission or programs</li>
                        <li>Subscribe to our newsletters</li>
                        <li>Create an account on our platform</li>
                        <li>Participate in surveys or feedback forms</li>
                    </ul>

                    <h3 class="text-xl font-semibold text-seait-dark mb-3">2.2 Automatically Collected Information</h3>
                    <p class="text-gray-700 mb-4">
                        When you visit our website, we automatically collect certain information about your device, including:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 mb-4 space-y-2">
                        <li>IP address and location data</li>
                        <li>Browser type and version</li>
                        <li>Operating system</li>
                        <li>Pages visited and time spent on pages</li>
                        <li>Referring website</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">3. How We Use Your Information</h2>
                    <p class="text-gray-700 mb-4">
                        We use the information we collect to:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 mb-4 space-y-2">
                        <li>Provide and maintain our website services</li>
                        <li>Process applications and inquiries</li>
                        <li>Send you important updates and announcements</li>
                        <li>Improve our website and user experience</li>
                        <li>Comply with legal obligations</li>
                        <li>Protect against fraud and security threats</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">4. Cookie Policy</h2>

                    <h3 class="text-xl font-semibold text-seait-dark mb-3">4.1 What Are Cookies</h3>
                    <p class="text-gray-700 mb-4">
                        Cookies are small text files that are placed on your device when you visit our website.
                        They help us provide you with a better experience and understand how you use our site.
                    </p>

                    <h3 class="text-xl font-semibold text-seait-dark mb-3">4.2 Types of Cookies We Use</h3>

                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h4 class="font-semibold text-seait-dark mb-2">Essential Cookies</h4>
                        <p class="text-gray-700 text-sm">
                            These cookies are necessary for the website to function properly. They enable basic functions
                            like page navigation and access to secure areas of the website.
                        </p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h4 class="font-semibold text-seait-dark mb-2">Analytics Cookies</h4>
                        <p class="text-gray-700 text-sm">
                            These cookies help us understand how visitors interact with our website by collecting
                            and reporting information anonymously.
                        </p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h4 class="font-semibold text-seait-dark mb-2">Preference Cookies</h4>
                        <p class="text-gray-700 text-sm">
                            These cookies allow the website to remember choices you make and provide enhanced,
                            more personal features.
                        </p>
                    </div>

                    <h3 class="text-xl font-semibold text-seait-dark mb-3">4.3 Managing Cookies</h3>
                    <p class="text-gray-700 mb-4">
                        You can control and/or delete cookies as you wish. You can delete all cookies that are
                        already on your computer and you can set most browsers to prevent them from being placed.
                        However, if you do this, you may have to manually adjust some preferences every time you
                        visit a site.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">5. Data Security</h2>
                    <p class="text-gray-700 mb-4">
                        We implement appropriate technical and organizational security measures to protect your
                        personal information against unauthorized access, alteration, disclosure, or destruction.
                        However, no method of transmission over the internet or electronic storage is 100% secure.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">6. Data Retention</h2>
                    <p class="text-gray-700 mb-4">
                        We retain your personal information only for as long as necessary to fulfill the purposes
                        outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">7. Your Rights</h2>
                    <p class="text-gray-700 mb-4">
                        You have the right to:
                    </p>
                    <ul class="list-disc list-inside text-gray-700 mb-4 space-y-2">
                        <li>Access your personal information</li>
                        <li>Correct inaccurate information</li>
                        <li>Request deletion of your information</li>
                        <li>Object to processing of your information</li>
                        <li>Request data portability</li>
                        <li>Withdraw consent where applicable</li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">8. Third-Party Services</h2>
                    <p class="text-gray-700 mb-4">
                        Our website may contain links to third-party websites or services. We are not responsible
                        for the privacy practices of these third parties. We encourage you to read their privacy policies.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">9. Children's Privacy</h2>
                    <p class="text-gray-700 mb-4">
                        Our website is not intended for children under 13 years of age. We do not knowingly collect
                        personal information from children under 13. If you are a parent or guardian and believe your
                        child has provided us with personal information, please contact us.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">10. Changes to This Policy</h2>
                    <p class="text-gray-700 mb-4">
                        We may update this Privacy Policy from time to time. We will notify you of any changes by
                        posting the new Privacy Policy on this page and updating the "Last updated" date.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-2xl font-semibold text-seait-dark mb-4">11. Contact Us</h2>
                    <p class="text-gray-700 mb-4">
                        If you have any questions about this Privacy Policy, please contact us:
                    </p>
                    <div class="bg-seait-light p-4 rounded-lg">
                        <p class="text-gray-700">
                            <strong>Email:</strong> privacy@seait.edu.ph<br>
                            <strong>Phone:</strong> +63 123 456 7890<br>
                            <strong>Address:</strong> 123 SEAIT Street, City, Philippines
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
    </script>
</body>
</html>