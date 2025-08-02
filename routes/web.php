<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/pricing', function () {
    $monthlyPlans = SubscriptionPlan::active()
        ->byInterval('month')
        ->ordered()
        ->get();
        
    $yearlyPlans = SubscriptionPlan::active()
        ->byInterval('year')
        ->ordered()
        ->get();

    return Inertia::render('Pricing', [
        'monthlyPlans' => $monthlyPlans,
        'yearlyPlans' => $yearlyPlans,
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('pricing');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/projects', function () {
    return Inertia::render('Projects');
})->name('projects');

Route::get('/security', function () {
    return Inertia::render('Security');
})->name('security');

Route::get('/sentiment', function () {
    return Inertia::render('Sentiment');
})->name('sentiment');

Route::get('/css-test', function () {
    return Inertia::render('CssTest');
})->name('css-test');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Subscription routes
Route::middleware(['auth'])->prefix('subscription')->name('subscription.')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('index');
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
    Route::put('/change', [SubscriptionController::class, 'change'])->name('change');
    Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
    Route::post('/resume', [SubscriptionController::class, 'resume'])->name('resume');
    
    // Invoice management
    Route::get('/invoices', [SubscriptionController::class, 'invoices'])->name('invoices');
    Route::get('/invoices/{invoice}', [SubscriptionController::class, 'downloadInvoice'])->name('invoices.download');
    
    // Payment methods
    Route::get('/payment-methods', [SubscriptionController::class, 'paymentMethods'])->name('payment-methods');
    Route::post('/payment-methods', [SubscriptionController::class, 'addPaymentMethod'])->name('payment-methods.add');
    Route::delete('/payment-methods', [SubscriptionController::class, 'removePaymentMethod'])->name('payment-methods.remove');
    Route::put('/payment-methods/default', [SubscriptionController::class, 'setDefaultPaymentMethod'])->name('payment-methods.default');
});

// Stripe webhook (public endpoint)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook')
    ->withoutMiddleware(['web', 'auth']);

require __DIR__.'/auth.php';
