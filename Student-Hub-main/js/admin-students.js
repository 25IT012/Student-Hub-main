/* ========================================================
   StudentHub - Admin Students Table Dynamic Controller
   ======================================================== */

document.addEventListener('DOMContentLoaded', () => {
    const fallbackStudents = [
        { id: 1, studentId: "STU2026001", name: "Pruthviraj", course: "Computer Engineering", semester: 2, status: "Active" },
        { id: 2, studentId: "STU2026002", name: "Rahul Patel", course: "Computer Engineering", semester: 4, status: "Active" },
        { id: 3, studentId: "STU2026003", name: "Priya Shah", course: "IT Engineering", semester: 2, status: "Active" },
        { id: 4, studentId: "STU2026004", name: "Aman Joshi", course: "Computer Engineering", semester: 6, status: "Pending" },
        { id: 5, studentId: "STU2026005", name: "Neha Mehta", course: "Electronics", semester: 4, status: "Active" },
        { id: 6, studentId: "STU2026006", name: "Ananya Sharma", course: "IT Engineering", semester: 6, status: "Active" },
        { id: 7, studentId: "STU2026007", name: "Karan Verma", course: "Mechanical Engineering", semester: 2, status: "Pending" },
        { id: 8, studentId: "STU2026008", name: "Sneha Roy", course: "Civil Engineering", semester: 4, status: "Active" },
        { id: 9, studentId: "STU2026009", name: "Devendra Kumar", course: "Electrical Engineering", semester: 8, status: "Inactive" },
        { id: 10, studentId: "STU2026010", name: "Riya Desai", course: "Computer Engineering", semester: 2, status: "Active" }
    ];

    let allStudents = [];
    let state = {
        query: '',
        course: 'All',
        currentPage: 1,
        pageSize: 4
    };

    const tbody = document.getElementById('studentsTableBody');
    const searchInput = document.getElementById('studentSearch');
    const courseSelect = document.getElementById('studentCourseFilter');
    const paginationContainer = document.getElementById('studentPagination');

    function render() {
        if (!tbody) return;

        // 1. Search
        let processed = DataService.search(allStudents, state.query, ['name', 'studentId', 'course']);

        // 2. Filter
        processed = DataService.filter(processed, 'course', state.course);

        // 3. Paginate
        const pageResult = DataService.paginate(processed, state.currentPage, state.pageSize);

        if (pageResult.items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" style="text-align: center; padding: 25px; color: #64748b;">
                        No students found matching your criteria.
                    </td>
                </tr>
            `;
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        tbody.innerHTML = pageResult.items.map(student => {
            const statusClass = (student.status || '').toLowerCase() === 'active' ? 'active-status' : 'pending-status';
            return `
                <tr>
                    <td>
                        <strong>${student.name}</strong><br>
                        <small style="color: #64748b;">${student.studentId}</small>
                    </td>
                    <td>${student.course}</td>
                    <td>Sem ${student.semester || 'N/A'}</td>
                    <td>
                        <span class="status ${statusClass}">
                            ${student.status}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

        if (paginationContainer) {
            DataService.renderPagination(paginationContainer, pageResult.totalPages, pageResult.currentPage, (newPage) => {
                state.currentPage = newPage;
                render();
            });
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            state.query = e.target.value;
            state.currentPage = 1;
            render();
        });
    }

    if (courseSelect) {
        courseSelect.addEventListener('change', (e) => {
            state.course = e.target.value;
            state.currentPage = 1;
            render();
        });
    }

    async function loadStudents() {
        allStudents = await DataService.fetchJson('../data/students.json', fallbackStudents);
        render();
    }

    loadStudents();
});
