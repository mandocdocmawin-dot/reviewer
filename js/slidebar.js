document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarMenu');
    const toggleBtn = document.getElementById('sidebarToggle');
    const body = document.body;
    
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
    }

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        
        body.classList.toggle('sidebar-collapsed');
        
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
});