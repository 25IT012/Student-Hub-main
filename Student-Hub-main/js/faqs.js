/* ========================================================
   StudentHub - FAQ Dynamic Search Controller
   ======================================================== */

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('faqSearchInput');
    const searchBtn = document.getElementById('faqSearchBtn');
    const faqBoxes = document.querySelectorAll('.faq-box');

    function filterFaqs() {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        faqBoxes.forEach(box => {
            const summary = box.querySelector('summary');
            const answer = box.querySelector('.faq-answer');
            const text = (summary ? summary.textContent : '') + ' ' + (answer ? answer.textContent : '');

            if (!query || text.toLowerCase().includes(query)) {
                box.style.display = '';
                if (query) {
                    box.open = true; // Auto open matching accordions
                }
            } else {
                box.style.display = 'none';
                box.open = false;
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterFaqs);
    }
    if (searchBtn) {
        searchBtn.addEventListener('click', (e) => {
            e.preventDefault();
            filterFaqs();
        });
    }
});
