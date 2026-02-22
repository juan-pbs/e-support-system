        // Initialize Lucide icons
        lucide.createIcons();

        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const desktopMenuBtn = document.getElementById('desktop-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleMobileMenu() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleDesktopMenu() {
            sidebar.classList.toggle('sidebar-collapsed');
        }

        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        desktopMenuBtn.addEventListener('click', toggleDesktopMenu);
        overlay.addEventListener('click', toggleMobileMenu);

        // Close mobile menu when clicking on a nav item
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleMobileMenu();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('sidebar-collapsed');
            }
        });