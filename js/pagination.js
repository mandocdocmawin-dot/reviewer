document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('review-list-container');

    container.addEventListener('click', function(e) {
        const link = e.target.closest('.pagination .page-link');
        
        if (link && !link.parentElement.classList.contains('disabled')) {
            e.preventDefault(); 
            
            const url = link.getAttribute('href');
            
            const separator = url.includes('?') ? '&' : '?';
            const ajaxUrl = url + separator + 'ajax=1';

            fetch(ajaxUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(html => {
                    container.innerHTML = html;
                    
                    window.history.pushState({}, '', url);
                })
                .catch(err => console.error('Pagination Error:', err));
        }
    });
});