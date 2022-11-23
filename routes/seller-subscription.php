<?php

use App\Http\Controllers\Seller\Addons\PackageController;
use App\Http\Controllers\Admin\Addons\PackageController as AdminPackageController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath','isInstalled']
    ], function () {
    Route::middleware(['adminCheck', 'loginCheck', 'XSS'])->prefix('admin')->group(function () {
        //Seller Package
        Route::resource('seller_packages',AdminPackageController::class)->except('show','destroy');
        Route::put('package-status-change', [AdminPackageController::class, 'statusChange'])->name('package.status.change');
        Route::delete('seller-packages/destroy',[AdminPackageController::class,'destroy'])->name('destroy');
    });

    Route::middleware(['sellerCheck', 'loginCheck'])->prefix('seller')->group(function () {
        Route::get('packages', [PackageController::class, 'index'])->name('seller.packages');
        Route::get('package-purchase/{id}', [PackageController::class, 'payment'])->name('packages.purchase');
    });

});
