/* ========================================================
   StudentHub - Global JavaScript, Theme & Navigation Controller
   ======================================================== */

// Apply saved theme preference on page initialization
function applySavedTheme() {
    const savedTheme = localStorage.getItem('studenthub_theme');
    const isDark = savedTheme === 'dark';
    
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    
    if (isDark) {
        document.documentElement.classList.add('dark-theme');
        if (document.body) document.body.classList.add('dark-theme');
    } else {
        document.documentElement.classList.remove('dark-theme');
        if (document.body) document.body.classList.remove('dark-theme');
    }
    
    updateThemeToggleButtons(isDark);
}

// Toggle between Light and Dark mode
function toggleDarkMode() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 
        (document.body && document.body.classList.contains('dark-theme') ? 'dark' : 'light');
    const isDark = currentTheme !== 'dark';
    
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark-theme', isDark);
    if (document.body) {
        document.body.classList.toggle('dark-theme', isDark);
    }
    
    // Save selection to localStorage
    localStorage.setItem('studenthub_theme', isDark ? 'dark' : 'light');
    
    // Update button text / icon
    updateThemeToggleButtons(isDark);
}

// Update all theme toggle buttons across the page
function updateThemeToggleButtons(isDark) {
    if (typeof isDark === 'undefined') {
        isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                 (document.body ? document.body.classList.contains('dark-theme') : false);
    }
    
    // Update legacy class-based buttons
    const buttons = document.querySelectorAll('.theme-toggle-btn');
    buttons.forEach(button => {
        button.innerHTML = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    });
    
    // Update icon-based theme toggle buttons
    const iconButtons = document.querySelectorAll('#themeToggleBtn, .theme-toggle');
    iconButtons.forEach(button => {
        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    });
}

// Immediate theme execution to prevent flash
(function() {
    try {
        const savedTheme = localStorage.getItem('studenthub_theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('dark-theme');
        } else if (savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.classList.remove('dark-theme');
        }
    } catch (e) {}
})();

// Mobile Hamburger Navigation Toggle
function setupMobileNav() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mainNav = document.getElementById('mainNav');
    
    if (hamburgerBtn && mainNav) {
        hamburgerBtn.addEventListener('click', () => {
            const isExpanded = mainNav.classList.toggle('active');
            hamburgerBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (event) => {
            if (!mainNav.contains(event.target) && !hamburgerBtn.contains(event.target) && mainNav.classList.contains('active')) {
                mainNav.classList.remove('active');
                hamburgerBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
}

// Load and render notices dynamically (for home page)
async function loadNotices() {
    const list = document.getElementById('noticesList');
    if (!list) return;

    const fallbackNotices = [
        { id: 1, title: "Lab Submission Deadline on Friday", date: "2026-10-09", priority: "High" },
        { id: 2, title: "Workshop Registration is Now Open", date: "2026-10-12", priority: "Medium" },
        { id: 3, title: "National Level Hackathon Announced", date: "2026-10-18", priority: "High" },
        { id: 4, title: "Mid-Semester Examination Schedule Released", date: "2026-10-25", priority: "High" }
    ];

    if (window.DataService) {
        const notices = await window.DataService.fetchJson('../data/notices.json', fallbackNotices);
        list.innerHTML = notices.map(n => {
            const badgeColor = n.priority === 'High' ? '#ef4444' : '#3b82f6';
            return `
                <li style="margin: 10px 0; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                    <span>📌 ${n.title}</span>
                    <span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: ${badgeColor}; color: white; white-space: nowrap;">${n.priority || 'Notice'}</span>
                </li>
            `;
        }).join('');
    }
}

// Dynamic Navigation Menu based on User Authentication & Role
async function setupRoleBasedNavigation() {
    const isPagesDir = window.location.pathname.includes('/pages/');
    const authCheckUrl = isPagesDir ? '../php/check_auth.php' : 'php/check_auth.php';
    const logoutUrl = isPagesDir ? '../php/logout.php' : 'php/logout.php';
    const adminUrl = isPagesDir ? 'admin.html' : 'pages/admin.html';
    const dashboardUrl = isPagesDir ? 'dashboard.html' : 'pages/dashboard.html';
    const loginUrl = isPagesDir ? 'login.html' : 'pages/login.html';

    try {
        const response = await fetch(authCheckUrl, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin'
        });

        const data = await response.json();
        const navList = document.querySelector('.nav-list') || document.querySelector('nav .nav-links') || document.querySelector('nav');
        const navActions = document.querySelector('.nav-actions');

        if (data && data.authenticated && data.user) {
            const role = data.user.role;
            const userName = data.user.name || 'User';

            // 1. ADMIN USER
            if (role === 'admin') {
                // Ensure Admin nav link is present and highlighted if on admin page
                if (navList && !navList.querySelector('a[href*="admin.html"]')) {
                    const adminLi = document.createElement('li');
                    adminLi.innerHTML = `<a href="${adminUrl}" class="nav-link ${window.location.pathname.includes('admin.html') ? 'active' : ''}">Admin Panel</a>`;
                    navList.appendChild(adminLi);
                }

                // Update Actions area
                if (navActions) {
                    const oldAuthBtns = navActions.querySelectorAll('a[href*="login"], a[href*="register"]');
                    oldAuthBtns.forEach(btn => btn.remove());

                    if (!navActions.querySelector('.user-auth-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'user-auth-badge admin-badge';
                        badge.style.cssText = 'display:inline-flex; align-items:center; gap:6px; padding:4px 10px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:9999px; font-size:0.75rem; font-weight:700;';
                        badge.innerHTML = `<i class="fa-solid fa-shield-halved"></i> Admin: ${escapeHtml(userName.split(' ')[0])}`;
                        navActions.insertBefore(badge, navActions.firstChild);
                    }
                }
            } else {
                // 2. STUDENT USER: Hide Admin panel from navigation
                document.querySelectorAll('header nav a[href*="admin.html"]').forEach(link => {
                    const parent = link.closest('li') || link;
                    parent.remove();
                });

                // Update Actions area with student greeting
                if (navActions) {
                    const oldAuthBtns = navActions.querySelectorAll('a[href*="login"], a[href*="register"]');
                    oldAuthBtns.forEach(btn => btn.remove());

                    if (!navActions.querySelector('.user-auth-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'user-auth-badge student-badge';
                        badge.style.cssText = 'display:inline-flex; align-items:center; gap:6px; padding:4px 10px; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:9999px; font-size:0.75rem; font-weight:700;';
                        badge.innerHTML = `<i class="fa-solid fa-user-graduate"></i> ${escapeHtml(userName.split(' ')[0])}`;
                        navActions.insertBefore(badge, navActions.firstChild);
                    }
                }
            }

            // Ensure Logout button exists in nav-actions
            if (navActions && !navActions.querySelector('a[href*="logout.php"]')) {
                const logoutBtn = document.createElement('a');
                logoutBtn.href = logoutUrl;
                logoutBtn.className = 'btn btn-outline btn-sm';
                logoutBtn.style.cssText = 'margin-left: 8px; font-size: 0.8rem; padding: 6px 12px;';
                logoutBtn.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket"></i> Logout';
                navActions.appendChild(logoutBtn);
            }

        } else {
            // 3. UNAUTHENTICATED GUEST: Hide Admin panel from top navigation
            document.querySelectorAll('header nav a[href*="admin.html"]').forEach(link => {
                const parent = link.closest('li') || link;
                parent.remove();
            });
        }
    } catch (err) {
        // Quiet fallback if offline
    }
}

function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
}

// Setup event listeners once DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    applySavedTheme();

    // Attach click listeners to all theme toggle buttons
    const toggleButtons = document.querySelectorAll('#themeToggleBtn, .theme-toggle, .theme-toggle-btn');
    toggleButtons.forEach(button => {
        button.addEventListener('click', toggleDarkMode);
    });

    setupMobileNav();
    loadNotices();
    setupRoleBasedNavigation();

    console.log("StudentHub Theme, Navigation & Role-Based Dynamic Menu active.");
});