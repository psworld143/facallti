<?php
include('configuration.php');
include('headers.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title>SEENS - Gate Scanner</title>
	<!-- Tailwind CSS CDN -->
	<script src="https://cdn.tailwindcss.com"></script>
	<!-- Font Awesome CDN -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- SweetAlert2 CDN -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- jQuery CDN -->
	<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
	
	<style>
		/* Custom styles for better orientation handling */
		@media screen and (orientation: landscape) and (max-height: 600px) {
			.landscape-compact {
				padding-top: 0.5rem;
				padding-bottom: 0.5rem;
			}
			.landscape-compact h1 {
				font-size: 1.25rem;
				margin-bottom: 0.5rem;
			}
			.landscape-compact .input-container {
				margin-bottom: 0.5rem;
			}
		}
		
		@media screen and (orientation: portrait) and (max-width: 768px) {
			.portrait-mobile {
				padding: 1rem;
			}
			.portrait-mobile h1 {
				font-size: 1.5rem;
			}
		}
		
		/* Smooth transitions */
		.transition-all {
			transition: all 0.3s ease-in-out;
		}
		
		/* Custom scrollbar */
		::-webkit-scrollbar {
			width: 6px;
		}
		::-webkit-scrollbar-track {
			background: #f1f5f9;
		}
		::-webkit-scrollbar-thumb {
			background: #cbd5e1;
			border-radius: 3px;
		}
		::-webkit-scrollbar-thumb:hover {
			background: #94a3b8;
		}
		
		/* Animated popup styles */
		.animated-popup {
			animation: popupSlideIn 0.5s ease-out;
		}
		
		@keyframes popupSlideIn {
			0% {
				transform: scale(0.3) translateY(-100px);
				opacity: 0;
			}
			50% {
				transform: scale(1.05) translateY(0);
				opacity: 0.8;
			}
			100% {
				transform: scale(1) translateY(0);
				opacity: 1;
			}
		}
		
		/* Popup image animation */
		.animated-popup img {
			animation: imageZoomIn 0.6s ease-out 0.2s both;
		}
		
		@keyframes imageZoomIn {
			0% {
				transform: scale(0.5);
				opacity: 0;
			}
			100% {
				transform: scale(1);
				opacity: 1;
			}
		}
		
		/* Enhanced photo animations */
		@keyframes photoAppear {
			0% {
				transform: scale(0) rotate(-10deg);
				opacity: 0;
			}
			50% {
				transform: scale(1.1) rotate(5deg);
				opacity: 0.8;
			}
			100% {
				transform: scale(1) rotate(0deg);
				opacity: 1;
			}
		}
		
		@keyframes photoZoom {
			0% {
				transform: scale(0.8);
				opacity: 0;
			}
			50% {
				transform: scale(1.05);
				opacity: 0.9;
			}
			100% {
				transform: scale(1);
				opacity: 1;
			}
		}
		
		.animate-photo-appear {
			animation: photoAppear 0.8s ease-out forwards;
		}
		
		.animate-photo-zoom {
			animation: photoZoom 1s ease-out 0.3s both;
		}
		
		/* Glowing border effect */
		.scanned-photo-container {
			box-shadow: 
				0 0 20px rgba(34, 197, 94, 0.3),
				0 0 40px rgba(16, 185, 129, 0.2),
				0 0 60px rgba(249, 115, 22, 0.1);
		}
		
		.scanned-photo-container:hover {
			box-shadow: 
				0 0 30px rgba(34, 197, 94, 0.4),
				0 0 60px rgba(16, 185, 129, 0.3),
				0 0 90px rgba(249, 115, 22, 0.2);
		}
		
		/* Responsive centering for scanner content */
		@media screen and (orientation: landscape) {
			#scannerContent {
				min-height: 100vh;
				padding: 0.5rem 0;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
			}
			#scannerContent > div {
				width: 100%;
				max-width: 95vw;
				display: flex;
				flex-direction: column;
				align-items: center;
			}
			#recentPhotosGrid {
				grid-template-columns: repeat(6, 1fr) !important;
				gap: 0.75rem !important;
				width: 100% !important;
				max-width: 95vw !important;
				margin: 0 auto !important;
				justify-content: center !important;
			}
			#recentPhotosGrid img {
				max-height: 100px !important;
				height: 100px !important;
				width: 100% !important;
				object-fit: cover !important;
			}
			#recentPhotosGrid > div {
				padding: 0.5rem !important;
				min-width: 0 !important;
			}
			#scanned_picture {
				display: flex;
				justify-content: center;
				width: 100%;
			}
			#scanned_picture img {
				max-height: 60vh;
			}
			#scanned_picture > div {
				display: flex;
				justify-content: center;
				width: 100%;
			}
		}
		
		/* Additional landscape optimizations for different screen sizes */
		@media screen and (orientation: landscape) and (min-width: 1024px) {
			#recentPhotosGrid {
				grid-template-columns: repeat(8, 1fr) !important;
				gap: 1rem !important;
			}
			#recentPhotosGrid img {
				max-height: 120px !important;
				height: 120px !important;
			}
		}
		
		@media screen and (orientation: landscape) and (max-width: 768px) {
			#recentPhotosGrid {
				grid-template-columns: repeat(4, 1fr) !important;
				gap: 0.5rem !important;
			}
			#recentPhotosGrid img {
				max-height: 80px !important;
				height: 80px !important;
			}
		}
		
		@media screen and (orientation: portrait) {
			#scannerContent {
				min-height: 100vh;
				padding: 2rem 0;
			}
		}
	</style>
</head>
<body class="bg-black min-h-screen font-sans">
	<!-- Main Container -->
	<div class="min-h-screen flex flex-col">
		

		<!-- Main Content -->
		<main class="flex-1 flex flex-col justify-start pt-4 relative">
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 portrait-mobile">
				
				
				<!-- QR Code Input Section -->
				<div class="text-center mb-4 landscape-compact:mb-3 relative z-60">
					<div class="max-w-md mx-auto">
						<div class="relative">
							<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
								<i class="fas fa-search text-transparent"></i>
							</div>
							<input type="text" 
								name="QRCode" 
								id="barcode_search" 
								autofocus 
								class="w-full pl-10 pr-4 py-4 sm:py-5 text-lg sm:text-xl text-center bg-transparent border-2 border-transparent rounded-xl shadow-lg focus:outline-none focus:ring-0 focus:border-transparent transition-all duration-500 ease-in-out placeholder-transparent text-transparent"
								placeholder="Scan QR Code or Enter ID">
							<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
								<i class="fas fa-qrcode text-transparent"></i>
							</div>
						</div>
					</div>
				</div>

				<!-- Developer Animation Section -->
				<div id="developerPhoto" class="absolute inset-0 z-50 flex items-center justify-center transition-all duration-500 ease-in-out" style="top: 120px; bottom: 0;">
					<div class="w-full h-full flex items-center justify-center">
						<div class="w-full h-full flex items-center justify-center bg-black relative">
							<img src="dev.gif" 
								class="w-full h-full object-cover transition-all duration-500 ease-in-out" 
								alt="Developer Animation">
							<div class="absolute inset-0 bg-black/20"></div>
						</div>
					</div>
				</div>

				<!-- Scanner Content Section -->
				<div id="scannerContent" class="text-center relative z-20 flex flex-col items-center justify-center min-h-screen">
					<!-- Main Scanned Picture -->
					<div class="mb-4 landscape-compact:mb-3 w-full max-w-4xl mx-auto flex justify-center">
						<div id="scanned_picture" class="w-full flex justify-center">
							<!-- Content will be dynamically inserted -->
						</div>
					</div>

					<!-- Recent Pictures Grid -->
					<div class="w-full max-w-6xl mx-auto flex flex-col items-center">
						<h3 class="text-base font-semibold text-gray-300 mb-3 landscape-compact:mb-2">
							<i class="fas fa-history mr-2 text-blue-600"></i>
							Recent Scans
						</h3>
						<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4 w-full max-w-4xl mx-auto" id="recentPhotosGrid">
							<!-- Photo containers will be dynamically generated -->
						</div>
					</div>
				</div>
			</div>
		</main>

		<!-- Footer -->
		<footer class="bg-gradient-to-r from-gray-900 to-gray-800 text-white py-6 sm:py-8 px-4 mt-8 sm:mt-12">
			<div class="max-w-7xl mx-auto">
				<div class="flex flex-col md:flex-row justify-center items-center space-y-3 md:space-y-0 md:space-x-6 lg:space-x-8">
					<div class="flex items-center space-x-2 sm:space-x-3">
						<img src="cict.jpg" class="w-6 h-6 sm:w-8 sm:h-8 rounded-full shadow-lg" alt="CICT Logo">
						<span class="text-xs sm:text-sm font-medium text-center">College of Information and Communication Technology</span>
					</div>
					<div class="text-gray-400 hidden md:block">|</div>
					<div class="flex items-center space-x-2 sm:space-x-3">
						<span class="text-xs sm:text-sm font-medium text-center">Safety and Security Office</span>
						<img src="sso.jpg" class="w-6 h-6 sm:w-8 sm:h-8 rounded-full shadow-lg" alt="SSO Logo">
					</div>
				</div>
				<div class="text-center mt-3 sm:mt-4">
					<p class="text-xs text-gray-400">© 2024 SEENS - Student Entry and Exit Notification System</p>
				</div>
			</div>
		</footer>
	</div>

	<script>
		let timerIntervalScanner;
		
		$(document).ready(function() {
			$('#scannerContent').hide();
			$('#barcode_search').focus();
			
			// Add loading animation
			$('#barcode_search').on('focus', function() {
				$(this).addClass('animate-pulse');
			}).on('blur', function() {
				$(this).removeClass('animate-pulse');
			});
			
			// Add smooth entrance animation for standby screen
			$('#developerPhoto').hide().fadeIn(800);
			
			// Auto fullscreen functionality
			function enterFullscreen() {
				if (document.documentElement.requestFullscreen) {
					document.documentElement.requestFullscreen();
				} else if (document.documentElement.webkitRequestFullscreen) {
					document.documentElement.webkitRequestFullscreen();
				} else if (document.documentElement.msRequestFullscreen) {
					document.documentElement.msRequestFullscreen();
				}
			}
			
			// Try to enter fullscreen on page load
			setTimeout(function() {
				enterFullscreen();
			}, 1000); // 1 second delay to ensure page is fully loaded
			
			// Add fullscreen button for manual control
			$('body').append('<button id="fullscreenBtn" class="fixed top-4 right-4 z-50 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-lg transition-all duration-300 opacity-75 hover:opacity-100" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>');
			
			$('#fullscreenBtn').click(function() {
				if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
					enterFullscreen();
					$(this).html('<i class="fas fa-compress"></i>');
				} else {
					if (document.exitFullscreen) {
						document.exitFullscreen();
					} else if (document.webkitExitFullscreen) {
						document.webkitExitFullscreen();
					} else if (document.msExitFullscreen) {
						document.msExitFullscreen();
					}
					$(this).html('<i class="fas fa-expand"></i>');
				}
			});
			
			// Update fullscreen button icon when fullscreen state changes
			$(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
				if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
					$('#fullscreenBtn').html('<i class="fas fa-compress"></i>');
				} else {
					$('#fullscreenBtn').html('<i class="fas fa-expand"></i>');
				}
			});
		});

		window.addEventListener("keydown", function (event) {
			clearInterval(timerIntervalScanner);
			if (event.key == 'Enter') {
				// Show scanned pictures with smooth transition
				$('#developerPhoto').fadeOut(500, function() {
					$('#scannerContent').fadeIn(500);
				});
				
				var idQR = $("#barcode_search").val();
				$("#barcode_search").val('');
				
				if(idQR != ''){
					// Add loading state
					$('#barcode_search').addClass('animate-pulse').prop('disabled', true);
					
					$.ajax({
						type: "POST",
						url: "backend_scripts/check_id.php",
						data: {'token': 'Seait123', 'qr': idQR},
						success: function(resCheck) {
							$('#barcode_search').removeClass('animate-pulse').prop('disabled', false);
							
							// Check if response is already an object (jQuery might auto-parse JSON)
							var jsonData;
							if (typeof resCheck === 'object') {
								jsonData = resCheck;
							} else {
								try {
									jsonData = JSON.parse(resCheck);
								} catch (e) {
									Swal.fire({
										icon: 'error',
										title: 'Data Error',
										text: 'Invalid response from server. Please try again.',
										confirmButtonText: 'OK'
									});
									return;
								}
							}
							
							var stats = jsonData.message;
							
							if(stats != '0'){
								// Success - show student image with enhanced animation and frame
								document.getElementById('scanned_picture').innerHTML = 
									'<div class="scanned-photo-container bg-gradient-to-br from-green-900/90 via-emerald-900/90 to-orange-900/90 backdrop-blur-sm rounded-3xl shadow-2xl p-6 sm:p-8 border-2 border-gradient-to-r from-green-400 via-emerald-400 to-orange-400 w-full max-w-4xl mx-auto transform scale-0 animate-photo-appear">' +
									'<div class="relative">' +
									'<div class="absolute inset-0 bg-gradient-to-r from-green-500/20 via-emerald-500/20 to-orange-500/20 rounded-2xl animate-pulse"></div>' +
									'<div class="relative bg-white/10 backdrop-blur-sm rounded-2xl p-4 border border-white/20">' +
									'<img class="w-full h-auto max-h-96 sm:max-h-[500px] md:max-h-[600px] lg:max-h-[700px] xl:max-h-[800px] rounded-xl shadow-2xl transition-all duration-500 hover:scale-105 object-contain animate-photo-zoom" src="'+stats+'" alt="Scanned Student"/>' +
									'</div>' +
									'<div class="absolute -top-3 -right-3 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center animate-bounce">' +
									'<i class="fas fa-check text-white text-sm"></i>' +
									'</div>' +
									'<div class="absolute -bottom-3 -left-3 w-12 h-12 bg-gradient-to-r from-green-500 to-orange-500 rounded-full flex items-center justify-center animate-pulse">' +
									'<i class="fas fa-user-graduate text-white text-lg"></i>' +
									'</div>' +
									'</div>' +
									'</div>';
								
								// Play success sound
								var audioGranted = new Audio('Access Granted.mp3');
								audioGranted.play();
								
								// Success notification removed - no toast in upper right
								

								
								$("#barcode_search").val('');
								$('#barcode_search').focus();
								add_logs(idQR);
							} else {
								// Access denied
								$('#scannerContent').fadeOut(500, function() {
									$('#developerPhoto').fadeIn(500);
								});
								
								var audioDenied = new Audio('Access Denied.mp3');
								audioDenied.play();
								
								let timerInterval;
								Swal.fire({
									icon: 'error',
									title: "Access Denied",
									html: '<i class="fas fa-times-circle text-red-500 text-4xl mb-4"></i><br><span class="text-lg">Unregistered ID Scanned</span>',
									timer: 2000,
									timerProgressBar: true,
									showConfirmButton: false,
									background: '#fef2f2',
									color: '#dc2626'
								});
							}
						},
						error: function(xhr, status, error) {
							$('#barcode_search').removeClass('animate-pulse').prop('disabled', false);
							Swal.fire({
								icon: 'error',
								title: 'Connection Error',
								text: 'Unable to verify ID. Please try again.',
								confirmButtonText: 'OK'
							});
						}
					});
				} else {
					$('#scannerContent').fadeOut(500, function() {
						$('#developerPhoto').fadeIn(500);
					});
				}
			}
		});

		function add_logs(id){
			$.ajax({
				type: "POST",
				url: "backend_scripts/save_logs.php",
				data: {'token': 'Seait123', 'qr': id},
				success: function(resInsert) {   
					// Check if response is already an object (jQuery might auto-parse JSON)
					var jsonDataPictures;
					if (typeof resInsert === 'object') {
						jsonDataPictures = resInsert;
					} else {
						try {
							jsonDataPictures = JSON.parse(resInsert); 
						} catch (e) {
							// JSON parsing error in add_logs - silent fail
							return;
						}
					}
					

					
					// Clear and regenerate photo containers
					var gridContainer = document.getElementById('recentPhotosGrid');
					gridContainer.innerHTML = '';
					
					// Determine how many photos to show based on screen size
					var maxPhotos = 14; // Show up to 14 photos
					var photosToShow = jsonDataPictures && Array.isArray(jsonDataPictures) ? Math.min(jsonDataPictures.length, maxPhotos) : 0;
					
					// Create photo containers dynamically - only for actual photos
					for (var i = 0; i < photosToShow; i++) {
						var photoContainer = document.createElement('div');
						photoContainer.id = 'recent_picture' + (i + 1);
						photoContainer.className = 'bg-gray-800/80 backdrop-blur-sm rounded-xl shadow-lg border border-gray-600 p-3 hover:shadow-xl transition-all duration-500 ease-in-out hover:scale-105 hover:bg-gray-700/90';
						
						var placeholderDiv = document.createElement('div');
						placeholderDiv.className = 'h-32 sm:h-40 md:h-48 lg:h-56 xl:h-64 landscape:h-24 landscape:max-h-24 bg-gray-700 rounded-lg flex items-center justify-center transition-all duration-500 ease-in-out';
						
						var icon = document.createElement('i');
						icon.className = 'fas fa-user text-gray-400 text-2xl landscape:text-lg transition-all duration-500 ease-in-out';
						
						placeholderDiv.appendChild(icon);
						photoContainer.appendChild(placeholderDiv);
						gridContainer.appendChild(photoContainer);
					}
					
					// Populate with actual photos
					if(jsonDataPictures && Array.isArray(jsonDataPictures) && jsonDataPictures.length > 0){
						for (var j = 0; j < photosToShow; j++) {
							if(jsonDataPictures[j] && jsonDataPictures[j] != 'undefined' && jsonDataPictures[j] != ''){
								var photoElement = document.getElementById('recent_picture' + (j + 1));
								if (photoElement) {
									photoElement.innerHTML = 
										'<img class="w-full h-32 sm:h-40 md:h-48 lg:h-56 xl:h-64 landscape:h-24 landscape:max-h-24 object-cover rounded-lg shadow-sm transition-all duration-300 hover:scale-105" src="'+jsonDataPictures[j]+'" alt="Recent Student ' + (j + 1) + '" onerror="this.style.display=\'none\'"/>';
								}
							}
						}
					} else {
						// No recent photos data available
					}
				},
				error: function(xhr, status, error) {
					// AJAX Error in add_logs - silent fail for recent pictures
				}
			});
			
			// Auto-hide scanner content after 10 seconds
			timerIntervalScanner = setInterval(function() {
				$('#scannerContent').fadeOut(500, function() {
					$('#developerPhoto').fadeIn(500);
				});
			}, 10000); 
		}

		// Handle orientation change
		window.addEventListener('orientationchange', function() {
			setTimeout(function() {
				$('#barcode_search').focus();
			}, 500);
		});

		// Handle window resize
		window.addEventListener('resize', function() {
			// Refocus input after resize
			setTimeout(function() {
				$('#barcode_search').focus();
			}, 100);
		});
	</script>
</html>