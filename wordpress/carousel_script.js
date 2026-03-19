// Screenshots carousel functionality
// Add this script right before the closing </body> tag in your template

document.addEventListener('DOMContentLoaded', function() {
    var carousel = document.querySelector('.screenshots-carousel');
    if (carousel) {
        var container = carousel.querySelector('.screenshots-container');
        var screenshots = carousel.querySelectorAll('.screenshot');
        var prevBtn = carousel.querySelector('.carousel-nav.prev');
        var nextBtn = carousel.querySelector('.carousel-nav.next');
        var dots = carousel.querySelectorAll('.carousel-dot');
        var currentIndex = 0;
        var slidesToShow = Math.floor(carousel.offsetWidth / 300);
        var maxIndex = Math.max(0, screenshots.length - slidesToShow);
        
        function updateCarousel() {
            var translateX = currentIndex * -300;
            container.style.transform = 'translateX(' + translateX + 'px)';
            
            // Update navigation buttons
            prevBtn.disabled = currentIndex === 0;
            nextBtn.disabled = currentIndex >= maxIndex;
            
            // Update indicators
            dots.forEach(function(dot, index) {
                dot.classList.toggle('active', index === currentIndex);
            });
        }
        
        function moveCarousel(direction) {
            currentIndex += direction;
            if (currentIndex < 0) currentIndex = 0;
            if (currentIndex > maxIndex) currentIndex = maxIndex;
            updateCarousel();
        }
        
        function goToSlide(index) {
            currentIndex = Math.min(index, maxIndex);
            updateCarousel();
        }
        
        // Event listeners
        prevBtn.addEventListener('click', function() { moveCarousel(-1); });
        nextBtn.addEventListener('click', function() { moveCarousel(1); });
        
        dots.forEach(function(dot, index) {
            dot.addEventListener('click', function() { goToSlide(index); });
        });
        
        // Touch/swipe support
        var startX = 0;
        var isDragging = false;
        
        container.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
            isDragging = true;
        });
        
        container.addEventListener('touchmove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
        });
        
        container.addEventListener('touchend', function(e) {
            if (!isDragging) return;
            isDragging = false;
            
            var endX = e.changedTouches[0].clientX;
            var diff = startX - endX;
            
            if (Math.abs(diff) > 50) {
                moveCarousel(diff > 0 ? 1 : -1);
            }
        });
        
        // Mouse drag support
        container.addEventListener('mousedown', function(e) {
            startX = e.clientX;
            isDragging = true;
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
        });
        
        document.addEventListener('mouseup', function(e) {
            if (!isDragging) return;
            isDragging = false;
            
            var endX = e.clientX;
            var diff = startX - endX;
            
            if (Math.abs(diff) > 50) {
                moveCarousel(diff > 0 ? 1 : -1);
            }
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                moveCarousel(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                moveCarousel(1);
            }
        });
        
        // Window resize handler
        window.addEventListener('resize', function() {
            slidesToShow = Math.floor(carousel.offsetWidth / 300);
            maxIndex = Math.max(0, screenshots.length - slidesToShow);
            if (currentIndex > maxIndex) {
                currentIndex = maxIndex;
            }
            updateCarousel();
        });
        
        // Initialize
        updateCarousel();
    }
}); 