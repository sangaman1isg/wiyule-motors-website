 <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Wiyule Motors</h3>
                    <p class="text-gray-400">Premium automotive parts and services in Blantyre, Malawi since 2016.</p>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#hero" class="text-gray-400 hover:text-white transition">Home</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                        <li><a href="#booking" class="text-gray-400 hover:text-white transition">Book Now</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition">About</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Contact</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li>Nyambadwe, Blantyre</li>
                        <li>+265 993 575 111</li>
                        <li>Southern Region, Malawi</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Wiyule Motors. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // Initialize Feather Icons & AOS
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('active');
            });

            // Close mobile menu when clicking on a link
            const mobileLinks = mobileMenu.querySelectorAll('a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                });
            });
        }

        // Set minimum date for booking (today)
        const bookingDate = document.getElementById('bookingDate');
        if (bookingDate) {
            const today = new Date().toISOString().split('T')[0];
            bookingDate.setAttribute('min', today);
        }

        // Contact form submission with Formspree
        const contactForm = document.getElementById('contactForm');
        const formMessage = document.getElementById('formMessage');

        if (contactForm) {
            contactForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Show loading state
                const submitBtn = contactForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i data-feather="loader" class="w-5 h-5 inline-block animate-spin mr-2"></i>Sending...';
                submitBtn.disabled = true;

                // Get form data
                const formData = new FormData(contactForm);
                
                try {
                    // REPLACE 'YOUR_FORM_ID' with your actual Formspree form ID
                    const response = await fetch('https://formspree.io/f/maqdyrkl', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        formMessage.textContent = 'Thank you! We will get back to you soon.';
                        formMessage.className = 'mt-4 text-center text-green-600 font-semibold p-4 bg-green-50 rounded-xl';
                        formMessage.classList.remove('hidden');
                        contactForm.reset();
                    } else {
                        throw new Error('Form submission failed');
                    }
                } catch (error) {
                    formMessage.textContent = 'Oops! There was a problem. Please try again or call us directly.';
                    formMessage.className = 'mt-4 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                    formMessage.classList.remove('hidden');
                }
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                feather.replace();
                
                setTimeout(() => {
                    formMessage.classList.add('hidden');
                }, 5000);
            });
        }

        // Booking form submission with Formspree
        const bookingForm = document.getElementById('bookingForm');
        const bookingMessage = document.getElementById('bookingMessage');

        if (bookingForm) {
            bookingForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validate at least one service is selected
                const selectedServices = bookingForm.querySelectorAll('input[name="services"]:checked');
                const serviceError = document.getElementById('serviceError');
                
                if (selectedServices.length === 0) {
                    serviceError.classList.remove('hidden');
                    serviceError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                
                serviceError.classList.add('hidden');
                
                // Collect form data
                const formData = new FormData(bookingForm);
                
                // Add services as a comma-separated string
                const services = Array.from(selectedServices).map(s => s.value).join(', ');
                formData.set('services', services);

                // Show loading state
                const submitBtn = bookingForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i data-feather="loader" class="w-6 h-6 inline-block animate-spin mr-2"></i>Processing...';
                submitBtn.disabled = true;

                try {
                    // REPLACE 'YOUR_BOOKING_FORM_ID' with your actual Formspree form ID
                    const response = await fetch('https://formspree.io/f/xgolegbw', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        // Collect data for modal
                        const bookingData = {
                            name: formData.get('name'),
                            phone: formData.get('phone'),
                            email: formData.get('email'),
                            vehicle: {
                                make: formData.get('make'),
                                model: formData.get('model'),
                                year: formData.get('year'),
                                registration: formData.get('registration')
                            },
                            services: services.split(', '),
                            date: formData.get('date'),
                            time: formData.get('time'),
                            notes: formData.get('notes')
                        };

                        // Show confirmation modal
                        showBookingModal(bookingData);
                        
                        // Reset form
                        bookingForm.reset();
                        
                        // Clear selected services styling
                        document.querySelectorAll('.service-option.selected').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                    } else {
                        throw new Error('Booking submission failed');
                    }
                } catch (error) {
                    bookingMessage.textContent = 'Oops! There was a problem. Please try again or call us at +265 993 575 111.';
                    bookingMessage.className = 'mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                    bookingMessage.classList.remove('hidden');
                    
                    setTimeout(() => {
                        bookingMessage.classList.add('hidden');
                    }, 5000);
                }
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                feather.replace();
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && document.querySelector(href)) {
                    e.preventDefault();
                    document.querySelector(href).scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });

    // Toggle service selection styling
    function toggleService(element, serviceName) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        element.classList.toggle('selected');
        
        // Hide error if service is selected
        if (checkbox.checked) {
            document.getElementById('serviceError').classList.add('hidden');
        }
    }

    // Toggle FAQ
    function toggleFAQ(button) {
        const answer = button.nextElementSibling;
        const icon = button.querySelector('[data-feather="chevron-down"]');
        const isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
        
        // Close all other FAQs
        document.querySelectorAll('.faq-answer').forEach(item => {
            item.style.maxHeight = '0';
        });
        document.querySelectorAll('.faq-question [data-feather="chevron-down"]').forEach(item => {
            item.style.transform = 'rotate(0deg)';
        });
        
        // Toggle current FAQ
        if (!isOpen) {
            answer.style.maxHeight = answer.scrollHeight + 'px';
            icon.style.transform = 'rotate(180deg)';
        }
        
        feather.replace();
    }

    // Show booking confirmation modal
    function showBookingModal(data) {
        const modal = document.getElementById('bookingModal');
        const modalContent = document.getElementById('modalContent');
        
        // Format date
        const dateObj = new Date(data.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Create modal content
        modalContent.innerHTML = `
            <div class="space-y-6">
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Customer Details</h4>
                    <p class="text-gray-900 font-semibold">${data.name}</p>
                    <p class="text-gray-600">${data.phone}</p>
                    ${data.email ? `<p class="text-gray-600">${data.email}</p>` : ''}
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Vehicle Information</h4>
                    <p class="text-gray-900 font-semibold">${data.vehicle.year} ${data.vehicle.make} ${data.vehicle.model}</p>
                    ${data.vehicle.registration ? `<p class="text-gray-600">Registration: ${data.vehicle.registration}</p>` : ''}
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Selected Services</h4>
                    <ul class="space-y-1">
                        ${data.services.map(service => `<li class="text-gray-900">• ${service}</li>`).join('')}
                    </ul>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Appointment Time</h4>
                    <p class="text-gray-900 font-semibold">${formattedDate}</p>
                    <p class="text-gray-900">at ${formatTime(data.time)}</p>
                </div>
                
                ${data.notes ? `
                <div class="pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Additional Notes</h4>
                    <p class="text-gray-600">${data.notes}</p>
                </div>
                ` : ''}
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-sm text-blue-900">
                        <i data-feather="info" class="w-4 h-4 inline-block mr-1"></i>
                        We'll contact you shortly to confirm your appointment. You can also reach us directly at +265 993 575 111.
                    </p>
                </div>
            </div>
        `;
        
        modal.classList.add('active');
        feather.replace(); // Refresh feather icons in modal
    }

    // Close modal
    function closeModal() {
        const modal = document.getElementById('bookingModal');
        modal.classList.remove('active');
    }

    // Format time for display
    function formatTime(time) {
        const [hour, minute] = time.split(':');
        const hourNum = parseInt(hour);
        const ampm = hourNum >= 12 ? 'PM' : 'AM';
        const displayHour = hourNum > 12 ? hourNum - 12 : (hourNum === 0 ? 12 : hourNum);
        return `${displayHour}:${minute} ${ampm}`;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('bookingModal');
        if (event.target === modal) {
            closeModal();
        }
    }

    // Track scroll for analytics (optional)
    let scrollTracked = false;
    window.addEventListener('scroll', function() {
        if (!scrollTracked && window.scrollY > 500) {
            scrollTracked = true;
            // Track scroll event in analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'scroll', {
                    'event_category': 'engagement',
                    'event_label': 'scrolled_page'
                });
            }
        }
    });

    // Track booking button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('a[href="#booking"]')) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'conversion',
                    'event_label': 'booking_button_clicked'
                });
            }
        }
    });
    
    // Scroll indicator click   
    document.getElementById("scroll-indicator").addEventListener("click", function () {
    const nextSection = document.querySelector("#services");

    if (nextSection) {
        nextSection.scrollIntoView({
            behavior: "smooth"
        });
    }
});
    </script>
</body>
</html>