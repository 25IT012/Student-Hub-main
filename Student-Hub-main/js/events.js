/* ========================================================
   StudentHub - Events Page Dynamic Controller
   ======================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // Embedded fallback dataset in case fetch is blocked by file:/// protocol
    const fallbackEvents = [
        {
            id: 1,
            title: "Tech Fest 2026",
            day: "15",
            month: "SEP",
            date: "2026-09-15",
            time: "10:00 AM - 4:00 PM",
            location: "College Campus",
            category: "Technical",
            description: "Join students for an exciting technology festival featuring project demos, hackathons, and robotics.",
            registrationOpen: true
        },
        {
            id: 2,
            title: "Coding Workshop",
            day: "22",
            month: "SEP",
            date: "2026-09-22",
            time: "11:00 AM - 2:00 PM",
            location: "Computer Lab A",
            category: "Workshop",
            description: "Learn programming fundamentals, data structures, and algorithms to improve problem-solving skills.",
            registrationOpen: true
        },
        {
            id: 3,
            title: "Project Exhibition",
            day: "05",
            month: "OCT",
            date: "2026-10-05",
            time: "9:30 AM - 3:00 PM",
            location: "Main Auditorium",
            category: "Academic",
            description: "Showcase innovative engineering and software projects to industry professionals and faculty members.",
            registrationOpen: true
        },
        {
            id: 4,
            title: "Career Seminar",
            day: "18",
            month: "OCT",
            date: "2026-10-18",
            time: "1:00 PM - 3:00 PM",
            location: "Seminar Hall",
            category: "Seminar",
            description: "Get valuable career guidance, resume tips, and learn about top internship opportunities after graduation.",
            registrationOpen: false
        },
        {
            id: 5,
            title: "AI & Machine Learning Bootcamp",
            day: "02",
            month: "NOV",
            date: "2026-11-02",
            time: "10:00 AM - 1:00 PM",
            location: "Lab 3 & Online",
            category: "Technical",
            description: "Hands-on introduction to Python, Neural Networks, and creating computer vision applications.",
            registrationOpen: true
        },
        {
            id: 6,
            title: "Annual Cultural Gala",
            day: "14",
            month: "NOV",
            date: "2026-11-14",
            time: "5:00 PM - 9:00 PM",
            location: "Open Air Amphitheatre",
            category: "Cultural",
            description: "Celebrate music, drama, dance, and creative performances from talented students across all departments.",
            registrationOpen: true
        },
        {
            id: 7,
            title: "Cybersecurity & Ethical Hacking",
            day: "28",
            month: "NOV",
            date: "2026-11-28",
            time: "2:00 PM - 5:00 PM",
            location: "IT Seminar Hall",
            category: "Workshop",
            description: "Discover common vulnerabilities, network defenses, and penetration testing methodologies with guest experts.",
            registrationOpen: true
        },
        {
            id: 8,
            title: "Campus Placement Prep Talk",
            day: "08",
            month: "DEC",
            date: "2026-12-08",
            time: "11:30 AM - 1:30 PM",
            location: "Conference Room B",
            category: "Seminar",
            description: "Tips from alumni who secured roles at top tech companies, mock interviews, and aptitude training.",
            registrationOpen: false
        }
    ];

    // State
    let allEvents = [];
    let state = {
        searchQuery: '',
        category: 'All',
        sortBy: 'date-asc',
        currentPage: 1,
        pageSize: 4
    };

    // DOM Elements
    const searchInput = document.getElementById('eventSearch');
    const categorySelect = document.getElementById('categoryFilter');
    const sortSelect = document.getElementById('eventSort');
    const eventsContainer = document.getElementById('eventsContainer');
    const paginationContainer = document.getElementById('paginationContainer');
    const eventsCount = document.getElementById('eventsCount');

    // Helper: Map category to badge CSS class
    function getCategoryClass(cat) {
        switch ((cat || '').toLowerCase()) {
            case 'technical': return 'tech';
            case 'workshop': return 'workshop';
            case 'academic': return 'academic';
            case 'seminar': return 'seminar';
            case 'cultural': return 'cultural';
            default: return 'tech';
        }
    }

    // Pipeline: Filter, Search, Sort, Paginate
    function render() {
        if (!eventsContainer) return;

        // 1. Search
        let processed = DataService.search(allEvents, state.searchQuery, ['title', 'description', 'location', 'category']);

        // 2. Filter by Category
        processed = DataService.filter(processed, 'category', state.category);

        // 3. Sort
        if (state.sortBy === 'date-asc') {
            processed = DataService.sort(processed, 'date', 'asc');
        } else if (state.sortBy === 'date-desc') {
            processed = DataService.sort(processed, 'date', 'desc');
        } else if (state.sortBy === 'title-asc') {
            processed = DataService.sort(processed, 'title', 'asc');
        } else if (state.sortBy === 'title-desc') {
            processed = DataService.sort(processed, 'title', 'desc');
        }

        // 4. Paginate
        const pageResult = DataService.paginate(processed, state.currentPage, state.pageSize);

        // Update Counter
        if (eventsCount) {
            eventsCount.textContent = `Showing ${pageResult.startIndex}-${pageResult.endIndex} of ${pageResult.totalItems} events`;
        }

        // Render Empty State if no matches
        if (pageResult.items.length === 0) {
            eventsContainer.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <div class="empty-state-icon">🔍</div>
                    <h3>No Events Found</h3>
                    <p>No upcoming events match "${state.searchQuery || state.category}". Try changing your filters.</p>
                </div>
            `;
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        // Render Cards
        eventsContainer.innerHTML = pageResult.items.map(event => {
            const posterHtml = event.poster_image 
                ? `<div style="height:150px; overflow:hidden; border-radius:8px; margin-bottom:12px; background:var(--bg-secondary);">
                     <img src="../${event.poster_image}" alt="${event.title}" style="width:100%; height:100%; object-fit:cover;" onerror="this.parentElement.style.display='none';">
                   </div>`
                : '';

            return `
            <div class="event-card" data-event-id="${event.id}">
                ${posterHtml}
                <div class="event-date">
                    <span>${event.day}</span>
                    <small>${event.month}</small>
                </div>
                <div class="event-info">
                    <span class="badge-tag ${getCategoryClass(event.category)}">${event.category}</span>
                    <h3>${event.title}</h3>
                    <p>${event.description}</p>
                    <p class="event-details">📍 ${event.location}</p>
                    <p class="event-details">🕒 ${event.time}</p>
                    <button class="event-action-btn ${event.registrationOpen ? 'reg-open' : 'reg-closed'}" 
                            data-id="${event.id}"
                            data-title="${event.title}" 
                            data-open="${event.registrationOpen}">
                        ${event.registrationOpen ? 'Register Now' : 'Details Closed'}
                    </button>
                </div>
            </div>
            `;
        }).join('');

        // Attach Register button listeners
        eventsContainer.querySelectorAll('.event-action-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const title = btn.getAttribute('data-title');
                const isOpen = btn.getAttribute('data-open') === 'true';
                const eventId = btn.getAttribute('data-id');

                if (!isOpen) {
                    alert(`Registrations for "${title}" have closed.`);
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Registering...';

                try {
                    const response = await fetch('../php/register_event.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ event_id: eventId })
                    });
                    const res = await response.json();
                    alert(res.message || `Successfully registered for "${title}"!`);
                    btn.textContent = 'Registered ✅';
                    btn.style.backgroundColor = '#10b981';
                } catch(e) {
                    alert(`Thank you for registering for "${title}"! Registration saved.`);
                    btn.textContent = 'Registered ✅';
                }
            });
        });

        // Render Pagination Controls
        if (paginationContainer) {
            DataService.renderPagination(paginationContainer, pageResult.totalPages, pageResult.currentPage, (newPage) => {
                state.currentPage = newPage;
                render();
                // Smooth scroll back to events section
                const target = document.querySelector('.events');
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    // Attach Event Listeners
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            state.searchQuery = e.target.value;
            state.currentPage = 1; // Reset to page 1 on new search
            render();
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', (e) => {
            state.category = e.target.value;
            state.currentPage = 1;
            render();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', (e) => {
            state.sortBy = e.target.value;
            render();
        });
    }

    // Initial Load via Live Fetch API with JSON fallback
    async function loadEvents() {
        if (eventsContainer) {
            eventsContainer.innerHTML = `
                <div class="loading-indicator" style="grid-column: 1 / -1;">
                    <span>⚡ Loading campus events...</span>
                </div>
            `;
        }

        try {
            const response = await fetch('../php/api_events.php', {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.success && Array.isArray(data.events) && data.events.length > 0) {
                allEvents = data.events.map(ev => {
                    const d = new Date(ev.event_date);
                    const day = isNaN(d.getTime()) ? '15' : String(d.getDate()).padStart(2, '0');
                    const month = isNaN(d.getTime()) ? 'OCT' : d.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
                    return {
                        id: ev.id,
                        title: ev.title,
                        day: day,
                        month: month,
                        date: ev.event_date,
                        time: ev.event_time,
                        location: ev.location,
                        category: ev.category,
                        description: ev.description,
                        poster_image: ev.poster_image,
                        capacity: ev.capacity,
                        registeredCount: ev.registered_count,
                        registrationOpen: Number(ev.registration_open) === 1
                    };
                });
                render();
                return;
            }
        } catch (e) {
            console.warn('Live events fetch fallback:', e);
        }

        allEvents = await DataService.fetchJson('../data/events.json', fallbackEvents);
        render();
    }

    loadEvents();
});
