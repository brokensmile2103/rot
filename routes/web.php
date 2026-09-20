<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\DataExportController;
use App\Http\Controllers\Owner\InventoryController;
use App\Http\Controllers\Owner\LocationController;
use App\Http\Controllers\Owner\MenuController;
use App\Http\Controllers\Owner\ModifierGroupController;
use App\Http\Controllers\Owner\QuickSetupController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StaffController;
use App\Http\Controllers\Owner\TaxBookController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\Pos\CashBookController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\ShiftController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ===== INSTALL WIZARD (chặn tự động sau khi cài xong bởi middleware EnsureInstalled) =====
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('/database', [InstallController::class, 'showDatabaseForm'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/account', [InstallController::class, 'showAccountForm'])->name('account');
    Route::post('/account', [InstallController::class, 'saveAccount'])->name('account.save');
    Route::get('/location', [InstallController::class, 'showLocationForm'])->name('location');
    Route::post('/location', [InstallController::class, 'saveLocation'])->name('location.save');
    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});

// ===== AUTH =====
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::redirect('/', '/order');

// ===== MENU CÔNG KHAI (QR đặt món) — KHÔNG cần đăng nhập, throttle theo IP
// để chặn spam gửi yêu cầu hàng loạt =====
Route::middleware('throttle:60,1')->prefix('menu')->name('public.menu.')->group(function () {
    Route::get('/{token}', [PublicMenuController::class, 'show'])->name('show');
    Route::post('/{token}/gui-yeu-cau', [PublicMenuController::class, 'store'])->name('store');
});

// ===== POS (owner + staff) =====
Route::middleware('auth')->group(function () {
    Route::get('/order', [OrderController::class, 'index'])->name('pos.order');
    Route::post('/order/checkout', [OrderController::class, 'checkout'])->name('pos.checkout');
    Route::post('/order/luu-nhap', [OrderController::class, 'saveDraft'])->name('pos.draft.store');

    Route::get('/don-hang', [OrderController::class, 'orders'])->name('pos.orders.index');
    // Polling nhẹ (v1.1.3) — trình duyệt hỏi định kỳ xem có yêu cầu gọi món mới từ
    // khách (QR) không để hiện thông báo. Giới hạn 60 lần/phút/tài khoản: đủ dư
    // cho nhiều tab/thiết bị cùng mở (mỗi tab ~6 lần/phút) mà vẫn chặn được
    // script chạy sai/lặp vô hạn làm nặng máy chủ hosting rẻ.
    Route::get('/don-hang/yeu-cau/dang-cho', [OrderController::class, 'pendingCustomerRequests'])
        ->middleware('throttle:60,1')->name('pos.orders.requests.pending');
    Route::post('/don-hang/yeu-cau/{customerRequest}/nhan', [OrderController::class, 'acceptCustomerRequest'])->name('pos.orders.requests.accept');
    Route::post('/don-hang/yeu-cau/{customerRequest}/tu-choi', [OrderController::class, 'rejectCustomerRequest'])->name('pos.orders.requests.reject');
    Route::get('/don-hang/{order}/sua', [OrderController::class, 'edit'])->name('pos.orders.edit');
    Route::put('/don-hang/{order}', [OrderController::class, 'update'])->name('pos.orders.update');
    Route::put('/don-hang/{order}/luu-nhap', [OrderController::class, 'updateDraft'])->name('pos.draft.update');
    Route::post('/don-hang/{order}/huy', [OrderController::class, 'cancel'])->name('pos.orders.cancel');
    Route::get('/don-hang/{order}/in', [OrderController::class, 'receipt'])->name('pos.orders.receipt');
    Route::post('/don-hang/{order}/xuat-lai-hddt', [OrderController::class, 'retryEinvoice'])->name('pos.orders.einvoice.retry');
    Route::get('/khach-hang/tra-cuu', [OrderController::class, 'lookupCustomer'])->name('pos.customer.lookup');

    Route::get('/ca/mo', [ShiftController::class, 'create'])->name('shift.create');
    Route::post('/ca/mo', [ShiftController::class, 'store'])->name('shift.store');
    Route::get('/ca/dong', [ShiftController::class, 'showClose'])->name('shift.close.show');
    Route::post('/ca/dong', [ShiftController::class, 'close'])->name('shift.close');
    Route::get('/ca/dong/{shift}/ket-qua', [ShiftController::class, 'closeResult'])->name('shift.close.result');
    Route::get('/ca/lich-su', [ShiftController::class, 'history'])->name('shift.history');
    Route::get('/ca/lich-su/{shift}', [ShiftController::class, 'historyShow'])->name('shift.history.show');

    Route::get('/so-quy', [CashBookController::class, 'index'])->name('cashbook.index');
    Route::post('/so-quy', [CashBookController::class, 'store'])->name('cashbook.store');

    Route::get('/ho-so', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/ho-so', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/ho-so/mat-khau', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

// ===== OWNER ONLY =====
Route::middleware(['auth', 'role:owner'])->prefix('quan-ly')->name('owner.')->group(function () {
    Route::get('/thuc-don', [MenuController::class, 'index'])->name('menu.index');
    Route::post('/thuc-don/danh-muc', [MenuController::class, 'storeCategory'])->name('menu.category.store');
    Route::put('/thuc-don/danh-muc/{category}', [MenuController::class, 'updateCategory'])->name('menu.category.update');
    Route::post('/thuc-don/mon', [MenuController::class, 'storeProduct'])->name('menu.product.store');
    Route::put('/thuc-don/mon/{product}', [MenuController::class, 'updateProduct'])->name('menu.product.update');
    Route::post('/thuc-don/mon/{product}/an-hien', [MenuController::class, 'toggleAvailability'])->name('menu.product.toggle');
    Route::delete('/thuc-don/mon/{product}', [MenuController::class, 'deleteProduct'])->name('menu.product.delete');
    Route::post('/thuc-don/mon/{product}/khoi-phuc', [MenuController::class, 'restoreProduct'])->name('menu.product.restore');
    Route::post('/thuc-don/mon/{product}/size', [MenuController::class, 'storeVariant'])->name('menu.variant.store');
    Route::put('/thuc-don/bien-the/{variant}', [MenuController::class, 'updateVariant'])->name('menu.variant.update');
    Route::delete('/thuc-don/bien-the/{variant}', [MenuController::class, 'deleteVariant'])->name('menu.variant.delete');
    Route::post('/thuc-don/bien-the/{variant}/khoi-phuc', [MenuController::class, 'restoreVariant'])->name('menu.variant.restore');
    Route::post('/thuc-don/bien-the/{variant}/cong-thuc', [MenuController::class, 'storeRecipe'])->name('menu.recipe.store');
    Route::delete('/thuc-don/cong-thuc/{recipe}', [MenuController::class, 'deleteRecipe'])->name('menu.recipe.delete');
    Route::post('/thuc-don/mon/{product}/tuy-chon', [MenuController::class, 'attachModifierGroup'])->name('menu.modifier-group.attach');
    Route::delete('/thuc-don/mon/{product}/tuy-chon/{group}', [MenuController::class, 'detachModifierGroup'])->name('menu.modifier-group.detach');

    Route::get('/tuy-chon', [ModifierGroupController::class, 'index'])->name('modifier-groups.index');
    Route::post('/tuy-chon', [ModifierGroupController::class, 'storeGroup'])->name('modifier-groups.store');
    Route::delete('/tuy-chon/{group}', [ModifierGroupController::class, 'deleteGroup'])->name('modifier-groups.delete');
    Route::post('/tuy-chon/{group}/khoi-phuc', [ModifierGroupController::class, 'restoreGroup'])->name('modifier-groups.restore');
    Route::post('/tuy-chon/{group}/muc', [ModifierGroupController::class, 'storeModifier'])->name('modifier-groups.modifier.store');
    Route::delete('/tuy-chon/muc/{modifier}', [ModifierGroupController::class, 'deleteModifier'])->name('modifier-groups.modifier.delete');
    Route::post('/tuy-chon/muc/{modifier}/khoi-phuc', [ModifierGroupController::class, 'restoreModifier'])->name('modifier-groups.modifier.restore');
    Route::post('/tuy-chon/muc/{modifier}/mac-dinh', [ModifierGroupController::class, 'toggleDefaultModifier'])->name('modifier-groups.modifier.toggle-default');
    Route::post('/tuy-chon/muc/{modifier}/cong-thuc', [ModifierGroupController::class, 'storeModifierRecipe'])->name('modifier-groups.modifier.recipe.store');
    Route::delete('/tuy-chon/cong-thuc/{recipe}', [ModifierGroupController::class, 'deleteModifierRecipe'])->name('modifier-groups.modifier.recipe.delete');

    Route::get('/kho', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/kho/nhat-ky-dieu-chinh', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    Route::get('/kho/{ingredient}/lich-su-nhap', [InventoryController::class, 'stockInHistory'])->name('inventory.stock-in.history');
    Route::get('/kho/{ingredient}/lich-su-dieu-chinh', [InventoryController::class, 'adjustmentHistory'])->name('inventory.adjustments.history');
    Route::post('/kho', [InventoryController::class, 'storeIngredient'])->name('inventory.store');
    Route::put('/kho/{ingredient}', [InventoryController::class, 'updateIngredient'])->name('inventory.update');
    Route::delete('/kho/{ingredient}', [InventoryController::class, 'deleteIngredient'])->name('inventory.delete');
    Route::post('/kho/{ingredient}/khoi-phuc', [InventoryController::class, 'restoreIngredient'])->name('inventory.restore');
    Route::post('/kho/{ingredient}/nhap', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');

    Route::get('/bao-cao', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/bao-cao/xuat-csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/bao-cao/gio-cao-diem', [ReportController::class, 'peakHours'])->name('reports.peak-hours');
    // Sổ doanh thu (mẫu S1a-HKD) + theo dõi ngưỡng thuế hộ kinh doanh (v1.2.0)
    Route::get('/so-doanh-thu', [TaxBookController::class, 'index'])->name('tax.revenue-book');
    Route::get('/so-doanh-thu/in', [TaxBookController::class, 'print'])->name('tax.revenue-book.print');
    Route::get('/so-doanh-thu/xuat-csv', [TaxBookController::class, 'export'])->name('tax.revenue-book.export');
    Route::put('/so-doanh-thu/thong-tin', [TaxBookController::class, 'updateProfile'])->name('tax.profile');
    Route::post('/so-doanh-thu/ngoai-he-thong', [TaxBookController::class, 'storeExternal'])->name('tax.external.store');
    Route::delete('/so-doanh-thu/ngoai-he-thong/{external}', [TaxBookController::class, 'destroyExternal'])->name('tax.external.destroy');

    Route::get('/cai-dat', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/cai-dat/giao-dien', [SettingsController::class, 'updateAppearance'])->name('settings.appearance');
    Route::put('/cai-dat/in-hoa-don', [SettingsController::class, 'updateReceipt'])->name('settings.receipt');
    Route::put('/cai-dat/ngan-hang', [SettingsController::class, 'updateBank'])->name('settings.bank');
    Route::put('/cai-dat/tich-diem', [SettingsController::class, 'updateLoyalty'])->name('settings.loyalty');
    Route::put('/cai-dat/chi-phi', [SettingsController::class, 'updateCosts'])->name('settings.costs');
    Route::put('/cai-dat/hoa-don-dien-tu', [SettingsController::class, 'updateEinvoice'])->name('settings.einvoice');
    Route::put('/cai-dat/dat-mon-qr', [SettingsController::class, 'updateQrOrdering'])->name('settings.qr-ordering');
    Route::post('/cai-dat/dat-mon-qr/doi-ma', [SettingsController::class, 'regenerateQrToken'])->name('settings.qr-ordering.regenerate');
    Route::get('/cai-dat/xuat-du-lieu', [DataExportController::class, 'export'])->name('settings.data-export');
    Route::post('/cai-dat/thiet-lap-nhanh', [QuickSetupController::class, 'store'])->name('settings.quick-setup');

    Route::get('/khach-hang', [CustomerController::class, 'index'])->name('customers.index');

    Route::get('/xe', [LocationController::class, 'index'])->name('locations.index');
    Route::get('/xe/bao-cao', [LocationController::class, 'report'])->name('locations.report');
    Route::post('/xe', [LocationController::class, 'store'])->name('locations.store');
    Route::put('/xe/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::post('/xe/{location}/chuyen', [LocationController::class, 'switch'])->name('locations.switch');

    Route::get('/nhan-vien', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/nhan-vien/bang-cong', [StaffController::class, 'timesheet'])->name('staff.timesheet');
    Route::post('/nhan-vien', [StaffController::class, 'store'])->name('staff.store');
    Route::post('/nhan-vien/{user}/kich-hoat', [StaffController::class, 'toggleActive'])->name('staff.toggle');
    Route::post('/nhan-vien/{user}/doi-mat-khau', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
    Route::put('/nhan-vien/{user}/luong', [StaffController::class, 'updateSalary'])->name('staff.salary');
    Route::delete('/nhan-vien/{user}', [StaffController::class, 'remove'])->name('staff.remove');
});
