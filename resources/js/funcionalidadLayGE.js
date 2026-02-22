  document.addEventListener('DOMContentLoaded', function () {
            // Inicializar íconos
            lucide.createIcons();

            // Variables
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const openBtn = document.getElementById('open-sidebar');
            const closeBtn = document.getElementById('close-sidebar');

            // Funciones
            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
                setTimeout(() => lucide.createIcons(), 300);
            }

            // Eventos
            openBtn.addEventListener('click', toggleSidebar);
            closeBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);

            // Manejo de submenús CORREGIDO
            const menuItemsWithSubmenu = document.querySelectorAll('.menu-item-with-submenu > .menu-item-hover');

            menuItemsWithSubmenu.forEach(item => {
                item.addEventListener('click', function (e) {
                    // Evitar propagación si se hace clic en un enlace dentro del submenú
                    if (e.target.tagName === 'A') return;

                    const submenuId = this.getAttribute('data-submenu');
                    const submenu = document.getElementById(`submenu-${submenuId}`);
                    const chevron = this.querySelector('.submenu-chevron');

                    // Alternar estado del submenú
                    submenu.classList.toggle('open');
                    chevron.classList.toggle('rotate-90');

                    // Actualizar íconos después de cambiar el estado
                    lucide.createIcons();
                });
            });

            // Cerrar submenús cuando se hace clic fuera de ellos
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.menu-item-with-submenu')) {
                    document.querySelectorAll('.submenu').forEach(submenu => {
                        submenu.classList.remove('open');
                    });
                    document.querySelectorAll('.submenu-chevron').forEach(chevron => {
                        chevron.classList.remove('rotate-90');
                    });
                }
            });

            // Inicializar íconos de nuevo por si se agregan dinámicamente
            setTimeout(() => lucide.createIcons(), 500);
        });
