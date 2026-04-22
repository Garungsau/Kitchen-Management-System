/**
 * Chart.js Helper for Dashboard Reports
 * Provides simple chart rendering functions
 */

const chartColors = {
    primary: '#1d4ed8',
    success: '#15803d',
    danger: '#b91c1c',
    warning: '#92400e',
    info: '#0891b2',
    light: '#f3f4f6',
    text: '#111827'
};

/**
 * Initialize and load charts
 */
async function initializeCharts() {
    console.log('📊 Initializing dashboard charts...');
    
    // Load Chart.js if not already loaded
    if (typeof Chart === 'undefined') {
        await loadChartJS();
    }
    
    // Initialize available charts
    await loadWeeklyRegistrationChart();
    await loadMonthlyCostTrendChart();
    await loadFavoriteMealsChart();
    await loadDepartmentStatsChart();
    await loadDailyAttendanceChart();
}

/**
 * Load Chart.js library
 */
function loadChartJS() {
    return new Promise((resolve, reject) => {
        if (typeof Chart !== 'undefined') {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Chart.js'));
        document.head.appendChild(script);
    });
}

/**
 * Weekly registration rate chart
 */
async function loadWeeklyRegistrationChart() {
    const container = document.getElementById('weeklyRegistrationChart');
    if (!container) return;
    
    try {
        const response = await fetch('api/get_chart_data.php?endpoint=weekly_registration&days=30');
        const result = await response.json();
        
        if (result.status !== 'success') {
            console.warn('Weekly registration chart: no data');
            return;
        }
        
        const ctx = container.getContext('2d');
        const labels = result.data.map(d => d.date);
        const registeredData = result.data.map(d => d.registered);
        const checkedInData = result.data.map(d => d.checked_in);
        const noShowData = result.data.map(d => d.no_show);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Đã đăng ký',
                        data: registeredData,
                        borderColor: chartColors.primary,
                        backgroundColor: 'rgba(29, 78, 216, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Xác nhận (Check-in)',
                        data: checkedInData,
                        borderColor: chartColors.success,
                        backgroundColor: 'rgba(21, 128, 61, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 4
                    },
                    {
                        label: 'Vắng mặt',
                        data: noShowData,
                        borderColor: chartColors.danger,
                        backgroundColor: 'rgba(185, 28, 28, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Tỷ lệ đăng ký & xác nhận theo tuần (30 ngày gần nhất)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => Math.round(value)
                        }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Error loading weekly registration chart:', err);
    }
}

/**
 * Monthly cost trend chart
 */
async function loadMonthlyCostTrendChart() {
    const container = document.getElementById('monthlyCostTrendChart');
    if (!container) return;
    
    try {
        const response = await fetch('api/get_chart_data.php?endpoint=monthly_cost_trend');
        const result = await response.json();
        
        if (result.status !== 'success') return;
        
        const ctx = container.getContext('2d');
        const labels = result.data.map(d => d.month);
        const costData = result.data.map(d => d.estimated_cost);
        const mealsData = result.data.map(d => d.total_meals);
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tổng chi phí (₫)',
                        data: costData,
                        backgroundColor: chartColors.primary,
                        borderColor: 'rgba(29, 78, 216, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Số suất ăn',
                        data: mealsData,
                        type: 'line',
                        borderColor: chartColors.success,
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Xu hướng chi phí 6 tháng gần nhất'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Chi phí (₫)'
                        },
                        ticks: {
                            callback: value => value.toLocaleString('vi-VN')
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Số suất ăn'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Error loading cost trend chart:', err);
    }
}

/**
 * Favorite meals chart
 */
async function loadFavoriteMealsChart() {
    const container = document.getElementById('favoriteMealsChart');
    if (!container) return;
    
    try {
        const month = document.getElementById('reportMonth')?.value || new Date().toISOString().slice(0, 7);
        const response = await fetch(`api/get_chart_data.php?endpoint=favorite_meals&month=${month}`);
        const result = await response.json();
        
        if (result.status !== 'success' || !result.data.length) return;
        
        const ctx = container.getContext('2d');
        const meals = result.data.slice(0, 10);
        const labels = meals.map(m => m.meal_name || 'Chưa đặt tên');
        const data = meals.map(m => m.order_count);
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        '#1d4ed8', '#15803d', '#b91c1c', '#0891b2', '#92400e',
                        '#7c3aed', '#059669', '#dc2626', '#0284c7', '#ea580c'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: `Món ăn được yêu thích (${month})`
                    },
                    legend: {
                        display: true,
                        position: 'right'
                    }
                }
            }
        });
    } catch (err) {
        console.error('Error loading favorite meals chart:', err);
    }
}

/**
 * Department statistics chart
 */
async function loadDepartmentStatsChart() {
    const container = document.getElementById('departmentStatsChart');
    if (!container) return;
    
    try {
        const month = document.getElementById('reportMonth')?.value || new Date().toISOString().slice(0, 7);
        const response = await fetch(`api/get_chart_data.php?endpoint=department_statistics&month=${month}`);
        const result = await response.json();
        
        if (result.status !== 'success' || !result.data.length) return;
        
        const ctx = container.getContext('2d');
        const labels = result.data.map(d => d.department);
        const totalMeals = result.data.map(d => d.total_meals);
        const checkedIn = result.data.map(d => d.checked_in);
        const noShow = result.data.map(d => d.no_show);
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Xác nhận',
                        data: checkedIn,
                        backgroundColor: chartColors.success
                    },
                    {
                        label: 'Đã đăng ký',
                        data: totalMeals.map((t, i) => t - checkedIn[i] - noShow[i]),
                        backgroundColor: chartColors.info
                    },
                    {
                        label: 'Vắng mặt',
                        data: noShow,
                        backgroundColor: chartColors.danger
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    title: {
                        display: true,
                        text: `Thống kê theo phòng ban (${month})`
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            callback: value => Math.round(value)
                        }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Error loading department stats chart:', err);
    }
}

/**
 * Daily attendance trend chart
 */
async function loadDailyAttendanceChart() {
    const container = document.getElementById('dailyAttendanceChart');
    if (!container) return;
    
    try {
        const response = await fetch('api/get_chart_data.php?endpoint=daily_attendance&days=30');
        const result = await response.json();
        
        if (result.status !== 'success') return;
        
        const ctx = container.getContext('2d');
        const labels = result.data.map(d => d.date);
        const checkedInData = result.data.map(d => d.checked_in);
        const noShowData = result.data.map(d => d.no_show);
        
        new Chart(ctx, {
            type: 'area',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Xác nhận',
                        data: checkedInData,
                        borderColor: chartColors.success,
                        backgroundColor: 'rgba(21, 128, 61, 0.2)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Vắng mặt',
                        data: noShowData,
                        borderColor: chartColors.danger,
                        backgroundColor: 'rgba(185, 28, 28, 0.2)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Xu hướng xác nhận hàng ngày (30 ngày)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        ticks: {
                            callback: value => Math.round(value)
                        }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Error loading daily attendance chart:', err);
    }
}

/**
 * Export monthly report
 */
async function exportMonthlyReport(format = 'csv', groupBy = 'employee') {
    try {
        const month = document.getElementById('reportMonth')?.value || new Date().toISOString().slice(0, 7);
        const unitCost = document.getElementById('unitCost')?.value || 35000;
        
        const url = `api/export_monthly_report.php?month=${month}&format=${format}&group_by=${groupBy}&unit_cost=${unitCost}`;
        window.location.href = url;
    } catch (err) {
        alert('Lỗi xuất báo cáo: ' + err.message);
    }
}

/**
 * Refresh charts
 */
async function refreshCharts() {
    console.log('🔄 Refreshing charts...');
    await initializeCharts();
}
