/* ========================================================
   StudentHub - Dynamic Profile Controller
   ======================================================== */

document.addEventListener('DOMContentLoaded', async () => {
    const fallbackStudent = {
        name: "Pruthviraj",
        email: "pruthviraj@example.com",
        studentId: "STU2026001",
        course: "Computer Engineering",
        semester: "2nd Semester",
        attendance: "85%",
        projects: 4
    };

    let student = fallbackStudent;
    if (window.DataService) {
        const students = await window.DataService.fetchJson('../data/students.json', [fallbackStudent]);
        if (students && students.length > 0) {
            student = students[0]; // Active student profile
        }
    }

    // Populate profile DOM
    const nameEl = document.querySelector('.profile-header h1');
    if (nameEl && student.name) nameEl.textContent = student.name;

    const emailEl = document.getElementById('profileEmail');
    if (emailEl && student.email) emailEl.textContent = student.email;

    const idEl = document.getElementById('profileId');
    if (idEl && student.studentId) idEl.textContent = student.studentId;

    const courseEl = document.getElementById('profileCourse');
    if (courseEl && student.course) courseEl.textContent = student.course;

    const semEl = document.getElementById('profileSemester');
    if (semEl && student.semester) semEl.textContent = `${student.semester}${student.semester === 2 ? 'nd' : 'th'} Semester`;

    const attEl = document.getElementById('profileAttendance');
    if (attEl && student.attendance) attEl.textContent = student.attendance;
});
