/* ========================================================
   StudentHub - Data Service (Fetch, Search, Filter, Sort, Paginate)
   ======================================================== */

const DataService = {
    /**
     * Safely fetches JSON from an endpoint with built-in CORS fallback
     * @param {string} url - Path to JSON file
     * @param {Array} fallbackData - Embedded offline dataset
     * @returns {Promise<Array>}
     */
    async fetchJson(url, fallbackData = []) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            return data;
        } catch (error) {
            console.warn(`Fetch from "${url}" could not be completed (e.g. local file:/// CORS policy). Using fallback dataset.`, error);
            return fallbackData;
        }
    },

    /**
     * Filters items based on substring search across specified properties
     * @param {Array} items
     * @param {string} query
     * @param {Array<string>} fields
     * @returns {Array}
     */
    search(items, query, fields = []) {
        if (!query || query.trim() === '') return items;
        const q = query.trim().toLowerCase();
        return items.filter(item => {
            return fields.some(field => {
                const val = item[field];
                return val && val.toString().toLowerCase().includes(q);
            });
        });
    },

    /**
     * Filters items by matching a specific key with a target value
     * @param {Array} items
     * @param {string} key
     * @param {string} value
     * @returns {Array}
     */
    filter(items, key, value) {
        if (!value || value === 'All' || value === '') return items;
        return items.filter(item => item[key] === value);
    },

    /**
     * Sorts items by a given key and direction
     * @param {Array} items
     * @param {string} key
     * @param {string} direction 'asc' | 'desc'
     * @returns {Array}
     */
    sort(items, key, direction = 'asc') {
        const sorted = [...items];
        sorted.sort((a, b) => {
            let valA = a[key];
            let valB = b[key];

            // If string dates, compare date timestamps
            if (key === 'date') {
                valA = new Date(valA).getTime();
                valB = new Date(valB).getTime();
            } else if (typeof valA === 'string') {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }

            if (valA < valB) return direction === 'asc' ? -1 : 1;
            if (valA > valB) return direction === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    },

    /**
     * Slices dataset into pages
     * @param {Array} items
     * @param {number} page (1-indexed)
     * @param {number} pageSize
     * @returns {Object}
     */
    paginate(items, page = 1, pageSize = 4) {
        const totalItems = items.length;
        const totalPages = Math.ceil(totalItems / pageSize) || 1;
        const validPage = Math.min(Math.max(1, page), totalPages);
        const startIndex = (validPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalItems);
        const pagedItems = items.slice(startIndex, endIndex);

        return {
            items: pagedItems,
            totalItems,
            totalPages,
            currentPage: validPage,
            startIndex: totalItems > 0 ? startIndex + 1 : 0,
            endIndex
        };
    },

    /**
     * Renders standard pagination controls into a target element
     * @param {HTMLElement} container
     * @param {number} totalPages
     * @param {number} currentPage
     * @param {Function} onPageChange
     */
    renderPagination(container, totalPages, currentPage, onPageChange) {
        if (!container) return;
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="pagination-wrapper">';

        // Previous button
        html += `<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">← Prev</button>`;

        // Page number buttons
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        // Next button
        html += `<button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next →</button>`;

        html += '</div>';
        container.innerHTML = html;

        // Attach click handlers
        const buttons = container.querySelectorAll('.pagination-btn:not(:disabled)');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = parseInt(btn.getAttribute('data-page'), 10);
                if (!isNaN(targetPage) && targetPage !== currentPage) {
                    onPageChange(targetPage);
                }
            });
        });
    }
};

window.DataService = DataService;
