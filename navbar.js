function renderNavbar(role, activePage = '') {
    const navContainer = document.getElementById('navbar-container');
    let links = '';
    let brandLink = (window.AppRouter && window.AppRouter.routes && window.AppRouter.routes.home) ? window.AppRouter.routes.home : 'index.html';
    let brandName = 'Hệ thống quản lý bếp ăn CPC1';

    const noticeBell = `
        <li class="nav-item dropdown me-2" id="noticeBellWrapper">
            <button class="btn btn-link nav-link position-relative text-dark" id="noticeBellButton" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell-fill fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="noticeBellBadge" style="font-size: 11px;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 shadow-sm" style="width: 360px;" aria-labelledby="noticeBellButton">
                <div class="px-3 py-2 border-bottom fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-megaphone-fill text-primary"></i>
                    <span>Thông báo</span>
                </div>
                <div id="noticeDropdownEmpty" class="px-3 py-3 text-muted small text-center">Không có thông báo</div>
                <div class="list-group list-group-flush" id="noticeDropdownList" style="max-height: 340px; overflow-y: auto;"></div>
            </div>
        </li>`;

    const isActive = (page) => page === activePage ? 'active fw-bold text-primary' : 'text-dark';

    const avatarBtn = `<li class="nav-item ms-2 d-flex align-items-center"><button class="btn p-0 border-0 bg-transparent nav-link" onclick="openProfileModal()" aria-label="Hồ sơ"><img id="navUserAvatar" src="assets/img/profile-placeholder.svg" alt="Ảnh hồ sơ" class="rounded-circle border" style="width:36px;height:36px;object-fit:cover;"></button></li>`;
    const logoutBtn = `<li class="nav-item"><button class="btn btn-sm btn-outline-primary ms-2 fw-bold" onclick="logout()">Đăng xuất</button></li>`;

    if (role === 'employee' || role === 'student') {
        links = `
            ${noticeBell}
            <li class="nav-item"><a class="nav-link text-dark" href="employee_dashboard.html"><i class="bi bi-grid me-1"></i>Trang điều hành</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="face_management.html"><i class="bi bi-face-id me-1"></i>Khuôn mặt</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="process_guide.html"><i class="bi bi-book-half me-1"></i>Hướng dẫn</a></li>
            ${avatarBtn}
            ${logoutBtn}
        `;
        brandLink = (window.AppRouter && window.AppRouter.getDashboardByRole)
            ? window.AppRouter.getDashboardByRole('employee')
            : 'employee_dashboard.html';
    } 
    else if (role === 'admin') {
        links = `
            ${noticeBell}
            <li class="nav-item"><a class="nav-link text-dark" href="admin_dashboard.html#users"><i class="bi bi-people me-1"></i>Tài khoản</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="admin_dashboard.html#meals"><i class="bi bi-bar-chart me-1"></i>Suất ăn</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="admin_dashboard.html#finance"><i class="bi bi-cash-coin me-1"></i>Tài chính</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="admin_dashboard.html#notices"><i class="bi bi-megaphone me-1"></i>Thông báo</a></li>
            <li class="nav-item"><a class="nav-link text-dark" href="process_guide.html"><i class="bi bi-book-half me-1"></i>Hướng dẫn</a></li>
            ${logoutBtn}
        `;
        brandLink = (window.AppRouter && window.AppRouter.getDashboardByRole)
            ? window.AppRouter.getDashboardByRole('admin')
            : 'admin_dashboard.html';
    } 
    else if (role === 'kitchen_staff' || role === 'kitchen') {
        links = `
            ${noticeBell}
            <li class="nav-item"><a class="nav-link text-dark" href="kitchen_staff_dashboard.html#overview"><i class="bi bi-grid me-1"></i>Tổng quan</a></li>
            ${logoutBtn}
        `;
        brandLink = (window.AppRouter && window.AppRouter.getDashboardByRole)
            ? window.AppRouter.getDashboardByRole('kitchen_staff')
            : 'kitchen_staff_dashboard.html';
    } 
    else {
        links = `
            <li class="nav-item"><a class="nav-link ${isActive('home')}" href="index.html">Trang chủ</a></li>
            <li class="nav-item"><a class="nav-link ${isActive('features')}" href="index.html#features">Tính năng</a></li>
            <li class="nav-item"><a class="nav-link ${isActive('login')}" href="auth/login.html">Đăng nhập</a></li>
        `;
    }

    const pathPrefix = window.location.pathname.includes('/auth/') ? '../' : '';

    navContainer.innerHTML = `
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-secondary d-flex align-items-center" href="${brandLink}">
                <img src="${pathPrefix}assets/duet-logo.png" alt="Logo" width="32" height="32" class="me-2">
                ${brandName}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center" id="navLinks">
                    ${links}
                </ul>
            </div>
        </div>
    </nav>
    `;
}

