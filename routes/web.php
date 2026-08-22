<?php

use App\Http\Controllers\AccurateController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\BarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerKeepController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Settings\DailyAuthCodeController;
use App\Http\Controllers\Settings\GeneralSettingController;
use App\Http\Controllers\Settings\PosCategorySettingController;
use App\Http\Controllers\Settings\TierSettingsController;
use App\Http\Controllers\TransactionCheckerController;
use App\Http\Controllers\TransactionHistoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaiterPerformanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return redirect()->route('login');
});

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/redirect-after-login', function () {
        $user = Auth::user();
        if (($user->type ?? 'internal') === 'internal') {
            $configuredRoleRoute = $user->roles()
                ->whereNotNull('default_redirect_route')
                ->where('default_redirect_route', '!=', '')
                ->pluck('default_redirect_route')
                ->first(fn (string $routeName): bool => Route::has($routeName));

            // Determine default route based on role
            $fallbackRoute = match (true) {
                $user->hasRole('Waiter/Server') => 'waiter.scanner',
                $user->hasRole('DJ') => 'admin.song-requests.index',
                $user->hasRole('Kitchen') => 'admin.kitchen.index',
                $user->hasRole('Bar') => 'admin.bar.index',
                $user->hasRole('Cashier') => 'admin.pos.index',
                default => 'admin.dashboard',
            };

            $targetRoute = $configuredRoleRoute ?: $fallbackRoute;

            // If static API token is configured, no OAuth flow needed
            if (config('accurate.api_token')) {
                return redirect()->route($targetRoute);
            }

            if (str_starts_with($targetRoute, 'admin.') && ! session()->has('accurate_access_token')) {
                return redirect()->route('accurate.auth');
            }

            return redirect()->route($targetRoute);
        }

        return redirect('/login');
    })->name('login.redirect');

    require __DIR__.'/database.php';

    // Waiter Mobile App
    Route::prefix('waiter')->name('waiter.')->middleware(['database_selected', 'ensure_waiter'])->group(function () {
        require __DIR__.'/waiter.php';
    });

    Route::prefix('admin')->middleware(['database_selected', 'check.admin.role'])->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/sync', [\App\Http\Controllers\DashboardController::class, 'syncToday'])->name('dashboard.sync');
        Route::post('/switch-area', [\App\Http\Controllers\AreaSwitcherController::class, 'switchArea'])->name('switch-area');

        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });

        require __DIR__.'/sync.php';

        // Area Management
        Route::resource('areas', AreaController::class)->except(['show', 'create', 'edit']);

        // Role Management
        Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);

        // User Management
        Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
        Route::post('users/sync-accurate', [UserController::class, 'syncAccurateEmployees'])->name('users.sync-accurate');
        Route::post('users/{user}/sync-accurate', [UserController::class, 'syncAccurate'])->name('users.sync-accurate-single');

        // Table Management
        require __DIR__.'/tables.php';

        // Booking/Reservation Management
        require __DIR__.'/bookings.php';

        // Display Message Management
        require __DIR__.'/display-messages.php';

        // Song Request Management
        require __DIR__.'/song-requests.php';

        // Event Management
        require __DIR__.'/events.php';

        // Inventory Management
        require __DIR__.'/inventories.php';

        // Menu Management
        require __DIR__.'/menus.php';

        // Point of Sale
        require __DIR__.'/pos.php';

        // Printer
        require __DIR__.'/printer.php';

        // Kitchen Management
        Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index');
        Route::get('kitchen/fetch', [KitchenController::class, 'fetchOrders'])->name('kitchen.fetch');
        Route::patch('kitchen/item/{item}/toggle', [KitchenController::class, 'toggleItem'])->name('kitchen.toggle-item');
        Route::patch('kitchen/{order}/complete-all', [KitchenController::class, 'completeAll'])->name('kitchen.complete-all');
        Route::post('kitchen/end-day/sync-snapshot', [KitchenController::class, 'syncSnapshot'])->name('kitchen.end-day.sync-snapshot');
        Route::post('kitchen/end-day', [KitchenController::class, 'submitEndDay'])->name('kitchen.end-day');
        Route::get('kitchen/end-day/{history}/preview', [KitchenController::class, 'previewEndDay'])->name('kitchen.end-day.preview');
        Route::post('kitchen/end-day/{history}/reprint', [KitchenController::class, 'reprintEndDay'])->name('kitchen.end-day.reprint');

        // Bar Management
        Route::get('bar', [BarController::class, 'index'])->name('bar.index');
        Route::get('bar/fetch', [BarController::class, 'fetchOrders'])->name('bar.fetch');
        Route::patch('bar/item/{item}/toggle', [BarController::class, 'toggleItem'])->name('bar.toggle-item');
        Route::patch('bar/{order}/complete-all', [BarController::class, 'completeAll'])->name('bar.complete-all');
        Route::post('bar/end-day/sync-snapshot', [BarController::class, 'syncSnapshot'])->name('bar.end-day.sync-snapshot');
        Route::post('bar/end-day', [BarController::class, 'submitEndDay'])->name('bar.end-day');
        Route::get('bar/end-day/{history}/preview', [BarController::class, 'previewEndDay'])->name('bar.end-day.preview');
        Route::post('bar/end-day/{history}/reprint', [BarController::class, 'reprintEndDay'])->name('bar.end-day.reprint');

        // Customer Management
        Route::resource('customers', CustomerController::class)->except(['show', 'create', 'edit']);
        Route::post('customers/{customer}/sync-accurate', [CustomerController::class, 'syncAccurate'])->name('customers.sync-accurate');

        // Customer Keep
        Route::resource('customer-keep', CustomerKeepController::class)->except(['show', 'create', 'edit']);
        Route::patch('customer-keep/{customerKeep}/mark-used', [CustomerKeepController::class, 'markUsed'])->name('customer-keep.mark-used');
        Route::post('rewards/redeem', [RewardController::class, 'redeem'])->name('rewards.redeem');
        Route::delete('rewards/redemptions/{redemption}', [RewardController::class, 'cancelRedemption'])->name('rewards.redemptions.destroy');
        Route::resource('rewards', RewardController::class)->except(['show', 'create', 'edit']);

        // Transaction Checker
        Route::get('transaction-checker', [TransactionCheckerController::class, 'index'])->name('transaction-checker.index');
        Route::patch('transaction-checker/items/{item}/check', [TransactionCheckerController::class, 'checkItem'])->name('transaction-checker.check-item');
        Route::patch('transaction-checker/orders/{order}/check-all', [TransactionCheckerController::class, 'checkAll'])->name('transaction-checker.check-all');
        Route::patch('transaction-checker/bulk-check', [TransactionCheckerController::class, 'checkBulk'])->name('transaction-checker.bulk-check');

        // Transaction History
        Route::get('transaction-history', [TransactionHistoryController::class, 'index'])->name('transaction-history.index');
        Route::get('transaction-history/refresh', [TransactionHistoryController::class, 'refresh'])->name('transaction-history.refresh');
        Route::post('transaction-history/{order}/print', [TransactionHistoryController::class, 'print'])->name('transaction-history.print');
        Route::post('transaction-history/{order}/payment', [TransactionHistoryController::class, 'updatePayment'])->name('transaction-history.update-payment');
        Route::post('transaction-history/{order}/settle-debt', [TransactionHistoryController::class, 'settleDebt'])->name('transaction-history.settle-debt');
        Route::post('transaction-history/{order}/re-sync-accurate', [TransactionHistoryController::class, 'reSyncAccurate'])->name('transaction-history.reSyncAccurate');

        // End-day Recap
        Route::get('recap', [RecapController::class, 'index'])->name('recap.index');
        Route::get('recap/close-preview', [RecapController::class, 'closePreview'])->name('recap.close-preview');
        Route::post('recap/close-preview/print', [RecapController::class, 'printClosePreview'])->name('recap.close-preview.print');
        Route::get('recap/export', [RecapController::class, 'export'])->name('recap.export');
        Route::post('recap/close-export', [RecapController::class, 'closeAndExport'])->name('recap.close-export');
        Route::get('recap/history/{recapHistory}/export', [RecapController::class, 'exportHistory'])->name('recap.history.export');
        Route::get('recap/history/{recapHistory}/transactions', [RecapController::class, 'historyTransactions'])->name('recap.history.transactions');
        Route::post('recap/history/{recapHistory}/reprint', [RecapController::class, 'reprintHistory'])->name('recap.history.reprint');

        // Waiter Performance
        Route::get('waiter-performance', [WaiterPerformanceController::class, 'index'])->name('waiter-performance.index');
        Route::get('waiter-performance/{waiter}/monthly-history', [WaiterPerformanceController::class, 'monthlyHistory'])->name('waiter-performance.monthly-history');

        // Settings
        Route::get('settings', function () {
            return view('settings.index');
        })->name('settings.index');

        // Daily Auth Code
        Route::prefix('settings/daily-auth-code')->name('settings.daily-auth-code.')->group(function () {
            Route::get('/', [DailyAuthCodeController::class, 'index'])->name('index');
            Route::post('/regenerate', [DailyAuthCodeController::class, 'regenerate'])->name('regenerate');
            Route::post('/override', [DailyAuthCodeController::class, 'override'])->name('override');
            Route::delete('/override', [DailyAuthCodeController::class, 'clearOverride'])->name('clear-override');
            Route::post('/verify', [DailyAuthCodeController::class, 'verify'])->name('verify');
            Route::post('/send-email', [DailyAuthCodeController::class, 'sendEmail'])->name('send-email');
        });

        // Tier Settings
        Route::resource('settings/tier-settings', TierSettingsController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters(['tier-settings' => 'tier'])
            ->names('settings.tier-settings');

        // POS Category Settings
        Route::prefix('settings/pos-categories')->name('settings.pos-categories.')->group(function () {
            Route::get('/', [PosCategorySettingController::class, 'index'])->name('index');
            Route::post('/', [PosCategorySettingController::class, 'save'])->name('save');
        });

        // General Settings (tax & service charge)
        Route::prefix('settings/general')->name('settings.general.')->group(function () {
            Route::get('/', [GeneralSettingController::class, 'index'])->name('index');
            Route::put('/', [GeneralSettingController::class, 'update'])->name('update');
            Route::post('/test-email', [GeneralSettingController::class, 'sendTestEmail'])->name('test-email');
        });
    });
});

Route::get('/accurate/auth', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));
    $clientId = env('ACCURATE_CLIENT_ID');
    $query = http_build_query([
        'client_id' => $clientId,
        'response_type' => 'code',
        'redirect_uri' => route('accurate.callback'),
        'scope' => 'bank_transfer_view bank_transfer_save bill_of_material_view bill_of_material_save branch_view branch_save currency_view currency_save customer_save customer_view customer_category_view customer_category_save customer_claim_view customer_claim_save data_classification_view data_classification_save delivery_order_view delivery_order_save department_view department_save employee_view employee_save exchange_invoice_view exchange_invoice_save expense_accrual_view expense_accrual_save fob_view fob_save glaccount_view glaccount_save item_view item_save item_adjustment_view item_adjustment_save item_category_view item_category_save item_transfer_view item_transfer_save job_order_view job_order_save journal_voucher_view journal_voucher_save material_adjustment_view material_adjustment_save price_category_view price_category_save project_view project_save purchase_invoice_view purchase_invoice_save purchase_order_save purchase_order_view purchase_payment_view purchase_payment_save purchase_requisition_view purchase_requisition_save purchase_return_view purchase_return_save receive_item_view receive_item_save roll_over_view roll_over_save sales_invoice_view sales_invoice_save sales_order_save sales_order_view sales_quotation_view sales_quotation_save sales_receipt_view sales_receipt_save sales_return_view sales_return_save shipment_view shipment_save stock_opname_order_view stock_opname_order_save stock_opname_result_view stock_opname_result_save tax_view tax_save unit_view unit_save vendor_view vendor_save vendor_category_view vendor_category_save vendor_claim_view vendor_claim_save vendor_price_view vendor_price_save warehouse_view warehouse_save work_order_view work_order_save material_slip_view material_slip_save finished_good_slip_view finished_good_slip_save',
        'state' => $state,
    ]);

    return redirect(env('ACCURATE_API_URL').'/oauth/authorize?'.$query);
})->name('accurate.auth');

Route::get('/accurate/callback', [
    AccurateController::class,
    'handleCallback',
])->name('accurate.callback');
