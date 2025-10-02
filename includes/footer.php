<?php
// Get school information from database using correct setting keys
$school_name = get_school_setting($conn, 'site_name', 'SEAIT - South East Asian Institute of Technology');
$school_abbreviation = get_school_abbreviation($conn);
$school_address = get_school_setting($conn, 'contact_address', '123 SEAIT Street, Technology District, Metro Manila, Philippines 1234');
$school_phone = get_school_setting($conn, 'contact_phone', '+63 123 456 7890');
$school_email = get_school_setting($conn, 'contact_email', 'info@seait.edu.ph');
$school_description = get_school_setting($conn, 'site_description', 'Empowering minds, shaping futures through excellence in technology education. SEAIT is committed to providing innovative, industry-relevant programs that prepare students for successful careers in the digital age.');
$school_facebook = get_school_setting($conn, 'school_facebook', '#');
$school_twitter = get_school_setting($conn, 'school_twitter', '#');
$school_instagram = get_school_setting($conn, 'school_instagram', '#');
$school_linkedin = get_school_setting($conn, 'school_linkedin', '#');
?>
<!-- Footer -->
<footer class="bg-seait-dark text-white py-8 md:py-12 mt-16">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
            <div>
                <h3 class="text-lg md:text-xl font-semibold mb-4"><?php echo htmlspecialchars($school_abbreviation); ?></h3>
                <p class="text-gray-300 text-sm md:text-base"><?php echo htmlspecialchars($school_description); ?></p>
            </div>
            <div>
                <h4 class="font-semibold mb-4 text-sm md:text-base">Quick Links</h4>
                <ul class="space-y-2 text-gray-300 text-sm md:text-base">
                    <li><a href="index.php#about" class="hover:text-white transition">About Us</a></li>
                    <li><a href="index.php#academics" class="hover:text-white transition">Academic Programs</a></li>
                    <li><a href="index.php#admissions" class="hover:text-white transition">Admissions</a></li>
                    <li><a href="index.php#research" class="hover:text-white transition">Research</a></li>
                    <li><a href="index.php#news" class="hover:text-white transition">News & Events</a></li>
                    <li><a href="index.php#contact" class="hover:text-white transition">Contact Us</a></li>
                    <li><a href="pre-registration.php" class="hover:text-white transition">Pre-registration</a></li>
                    <li><a href="news.php" class="hover:text-white transition">All News</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4 text-sm md:text-base">Contact Info</h4>
                <ul class="space-y-2 text-gray-300 text-sm md:text-base">
                    <li><i class="fas fa-map-marker-alt mr-2"></i> <?php echo htmlspecialchars($school_address); ?></li>
                    <li><i class="fas fa-phone mr-2"></i> <?php echo htmlspecialchars($school_phone); ?></li>
                    <li><i class="fas fa-envelope mr-2"></i> <?php echo htmlspecialchars($school_email); ?></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4 text-sm md:text-base">Follow Us</h4>
                <div class="flex space-x-4">
                    <a href="<?php echo htmlspecialchars($school_facebook); ?>" class="text-gray-300 hover:text-white transition" target="_blank"><i class="fab fa-facebook text-lg md:text-xl"></i></a>
                    <a href="<?php echo htmlspecialchars($school_twitter); ?>" class="text-gray-300 hover:text-white transition" target="_blank"><i class="fab fa-twitter text-lg md:text-xl"></i></a>
                    <a href="<?php echo htmlspecialchars($school_instagram); ?>" class="text-gray-300 hover:text-white transition" target="_blank"><i class="fab fa-instagram text-lg md:text-xl"></i></a>
                    <a href="<?php echo htmlspecialchars($school_linkedin); ?>" class="text-gray-300 hover:text-white transition" target="_blank"><i class="fab fa-linkedin text-lg md:text-xl"></i></a>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-700 mt-6 md:mt-8 pt-6 md:pt-8 text-center text-gray-300">
            <p class="text-sm md:text-base">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All rights reserved.</p>
            <p class="mt-2 text-sm md:text-base">
                <a href="privacy.php" class="hover:text-white transition">Privacy Policy</a> |
                <a href="terms.php" class="hover:text-white transition">Terms of Service</a>
            </p>
        </div>
    </div>
</footer>