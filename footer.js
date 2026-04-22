function renderFooter() {
    const container = document.getElementById('footer-container');
    if (container) {
        container.innerHTML = `
        <footer class="footer-modern py-4 mt-auto">
            <div class="container">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5 text-center text-md-start">
                        <div class="footer-brand">Hệ thống quản lý bếp ăn CPC1</div>
                        <div class="footer-meta small">Chuẩn vận hành bếp ăn doanh nghiệp dược phẩm</div>
                    </div>
                    <div class="col-md-4 text-center small footer-meta">
                        <div><i class="bi bi-envelope me-1"></i>support.canteen@cpc1.local</div>
                        <div><i class="bi bi-telephone me-1"></i>Hotline nội bộ: 1900 1088</div>
                    </div>
                    <div class="col-md-3 text-center text-md-end small footer-meta">
                        <div>Phiên bản: v2.1.0</div>
                        <div>&copy; 2026 CPC1. Bảo lưu mọi quyền.</div>
                    </div>
                </div>
            </div>
        </footer>
        `;
    }
}

