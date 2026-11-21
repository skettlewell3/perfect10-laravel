<?php

use App\Http\Controllers\Api\ElsewhereProfileProvisioningController;
use App\Http\Middleware\VerifyElsewhereProvisioningSignature;
use Illuminate\Support\Facades\Route;

Route::post(
    '/integrations/elsewhere/profiles',
    ElsewhereProfileProvisioningController::class
)
    ->middleware(VerifyElsewhereProvisioningSignature::class)
    ->name('api.integrations.elsewhere.profiles');
