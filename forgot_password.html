/**
 * Advanced Menu Management Helper
 * Handles image upload, nutrition data, templates, and history
 */

// Nutrition data store
let currentMenuNutrition = {
    lunch: { calories: 0, protein_g: 0, carbs_g: 0, fat_g: 0 },
    dinner: { calories: 0, protein_g: 0, carbs_g: 0, fat_g: 0 }
};

// Menu templates
let menuTemplates = [];

/**
 * Initialize advanced menu system
 */
async function initAdvancedMenu() {
    console.log('📋 Initializing advanced menu system...');
    
    // Load templates
    await loadMenuTemplates();
    
    // Load history warnings
    await checkMealHistory();
}

/**
 * Upload meal image via canvas
 */
async function uploadMealImage(shift) {
    const canvas = document.getElementById(`canvas-${shift}`);
    if (!canvas) {
        alert('Canvas element not found');
        return;
    }

    canvas.toBlob(async (blob) => {
        const reader = new FileReader();
        reader.onload = async (e) => {
            try {
                const response = await fetch('api/upload_meal_image.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        image_data: e.target.result,
                        meal_date: document.getElementById('menuDateInput').value || new Date().toISOString().split('T')[0],
                        shift: shift
                    })
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    showNotification(`✓ Ảnh ${shift} được lưu thành công`, 'success');
                    document.getElementById(`imagePreview-${shift}`).innerHTML = 
                        `<img src="${result.image_url}" style="max-width: 200px; border-radius: 8px;">`;
                } else {
                    showNotification('Lỗi: ' + result.message, 'error');
                }
            } catch (err) {
                showNotification('Lỗi upload: ' + err.message, 'error');
            }
        };
        reader.readAsDataURL(blob);
    }, 'image/jpeg', 0.85);
}

/**
 * Save menu with nutrition info
 */
async function saveAdvancedMenu() {
    const date = document.getElementById('menuDateInput').value;
    const lunch = document.getElementById('inLunch').value.trim();
    const dinner = document.getElementById('inDinner').value.trim();

    if (!lunch && !dinner) {
        alert('Vui lòng nhập ít nhất một bữa ăn');
        return;
    }

    try {
        const response = await fetch('api/save_menu_advanced.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date: date,
                lunch: lunch,
                dinner: dinner,
                lunch_nutrition: currentMenuNutrition.lunch,
                dinner_nutrition: currentMenuNutrition.dinner,
                lunch_image_url: document.getElementById('lunchImageUrl')?.value || '',
                dinner_image_url: document.getElementById('dinnerImageUrl')?.value || ''
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('✓ Thực đơn được lưu thành công', 'success');
            document.getElementById('currentMenuId').value = result.menu_id;
        } else {
            showNotification('Lỗi: ' + result.message, 'error');
        }
    } catch (err) {
        showNotification('Lỗi: ' + err.message, 'error');
    }
}

/**
 * Load menu templates
 */
async function loadMenuTemplates() {
    try {
        const response = await fetch('api/manage_menu_templates.php?action=list');
        const result = await response.json();
        
        if (result.status === 'success') {
            menuTemplates = result.templates;
            renderTemplateList();
        }
    } catch (err) {
        console.error('Error loading templates:', err);
    }
}

/**
 * Render template selector
 */
function renderTemplateList() {
    const container = document.getElementById('templateSelector');
    if (!container) return;

    let html = '<div class="d-flex gap-2 flex-wrap">';
    
    menuTemplates.forEach(tpl => {
        html += `
            <div class="card" style="flex: 1; min-width: 200px;">
                <div class="card-body p-2">
                    <h6 class="card-title mb-1">${tpl.template_name}</h6>
                    <small class="text-muted">${tpl.description || 'Không có mô tả'}</small>
                    <div class="mt-2 d-grid gap-1">
                        <button class="btn btn-sm btn-primary" onclick="applyTemplate(${tpl.id})">
                            <i class="bi bi-check"></i> Áp dụng
                        </button>
                        <button class="btn btn-sm btn-info" onclick="viewTemplate(${tpl.id})">
                            <i class="bi bi-eye"></i> Xem
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    if (menuTemplates.length === 0) {
        html += '<p class="text-muted">Chưa có template nào. Tạo template đầu tiên bạn!</p>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Apply template
 */
async function applyTemplate(templateId) {
    const startDate = prompt('Ngày bắt đầu (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
    if (!startDate) return;

    try {
        const response = await fetch('api/manage_menu_templates.php?action=apply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_id: templateId,
                start_date: startDate
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification(`✓ ${result.message}`, 'success');
            // Reload menu
            const menuDate = document.getElementById('menuDateInput');
            if (menuDate) {
                const evt = new Event('change');
                menuDate.dispatchEvent(evt);
            }
        } else {
            showNotification('Lỗi: ' + result.message, 'error');
        }
    } catch (err) {
        showNotification('Lỗi: ' + err.message, 'error');
    }
}

/**
 * Check meal history for duplicates
 */
async function checkMealHistory() {
    try {
        const response = await fetch('api/get_menu_history.php?type=recent');
        const result = await response.json();
        
        if (result.status === 'success') {
            const grouped = result.grouped_by_meal || {};
            window.mealHistoryBySemblance = grouped;
            console.log('📊 Menu history loaded');
        }
    } catch (err) {
        console.error('Error checking history:', err);
    }
}

/**
 * Warn if meal is repeated frequently
 */
function warnDuplicateMeal(shift) {
    const mealInput = document.getElementById(`in${shift.charAt(0).toUpperCase() + shift.slice(1)}`);
    if (!mealInput) return;

    const mealName = mealInput.value.trim().toLowerCase();
    const history = window.mealHistoryBySemblance?.[mealName] || [];
    
    const warningEl = document.getElementById(`${shift}HistoryWarning`);
    if (!warningEl) return;

    if (history.length > 2) {
        warningEl.innerHTML = `
            <div class="alert alert-warning small py-2 mb-0">
                <i class="bi bi-exclamation-triangle"></i> 
                Món này phục vụ ${history.length} lần trong 30 ngày gần nhất!
                <br><small>${history.map(h => `${h.menu_date}`).join(', ')}</small>
            </div>
        `;
    } else {
        warningEl.innerHTML = '';
    }
}

/**
 * Update nutrition for a shift
 */
function updateNutrition(shift, field, value) {
    currentMenuNutrition[shift][field] = parseFloat(value) || 0;
    
    // Update display
    const totalCalsEl = document.getElementById(`${shift}TotalCals`);
    if (totalCalsEl) {
        totalCalsEl.textContent = currentMenuNutrition[shift].calories + ' kcal';
    }
}

/**
 * Display menu with images and nutrition
 */
async function displayEnhancedMenu(date) {
    try {
        const response = await fetch(`api/get_menu_enhanced.php?date=${date}&view=approved`);
        const result = await response.json();
        
        if (result.status === 'success') {
            displayMealInfo('lunch', result.lunch);
            displayMealInfo('dinner', result.dinner);
        }
    } catch (err) {
        console.error('Error displaying menu:', err);
    }
}

/**
 * Display single meal info with image and nutrition
 */
function displayMealInfo(shift, mealData) {
    const container = document.getElementById(`${shift}MenuDisplay`);
    if (!container) return;

    let html = '';
    
    if (mealData.name) {
        html += `
            <div class="card mb-2">
                <div class="card-body p-2">
                    <div class="row">
                        <div class="col-md-4">
                            ${mealData.image_url ? 
                                `<img src="${mealData.image_url}" class="img-fluid rounded" style="max-height: 150px; object-fit: cover;">` 
                                : '<div style="height: 150px; background: #f0f0f0; border-radius: 8px;"></div>'}
                        </div>
                        <div class="col-md-8">
                            <h6 class="mb-1"><strong>${mealData.name}</strong></h6>
                            <div class="small text-muted">
                                <div>🔥 ${mealData.nutrition.calories} kcal</div>
                                <div>🥛 Protein: ${mealData.nutrition.protein_g}g</div>
                                <div>🍞 Carbs: ${mealData.nutrition.carbs_g}g</div>
                                <div>🍈 Fat: ${mealData.nutrition.fat_g}g</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        html = '<p class="text-muted">Chưa có thực đơn</p>';
    }
    
    container.innerHTML = html;
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'info': 'alert-info',
        'warning': 'alert-warning'
    }[type] || 'alert-info';

    const container = document.getElementById('menuMsg') || document.getElementById('menuNotification');
    if (!container) {
        alert(message);
        return;
    }

    container.innerHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

/**
 * Create new template
 */
async function createNewTemplate() {
    const name = prompt('Tên template:');
    if (!name) return;

    const desc = prompt('Mô tả (tùy chọn):');

    try {
        const response = await fetch('api/manage_menu_templates.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_name: name,
                description: desc || ''
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification(`✓ Template "${name}" được tạo`, 'success');
            await loadMenuTemplates();
        } else {
            showNotification('Lỗi: ' + result.message, 'error');
        }
    } catch (err) {
        showNotification('Lỗi: ' + err.message, 'error');
    }
}
