<?php

if ( ! defined( 'PAGSEGURO_CONTROLER')) {
    define('PAGSEGURO_CONTROLER', 'Cagartner\Pagseguro\Http\Controllers\PagseguroController@');
}

Route::group(['middleware' => ['web']], function () {
    Route::prefix('pagseguro')->group(function () {
        Route::get('/redirect', PAGSEGURO_CONTROLER . 'redirect')->name('pagseguro.redirect');
        Route::post('/notify', PAGSEGURO_CONTROLER . 'notify')->name('pagseguro.notify');
        Route::get('/success', PAGSEGURO_CONTROLER . 'success')->name('pagseguro.success');
        Route::get('/cancel', PAGSEGURO_CONTROLER . 'cancel')->name('pagseguro.cancel');
    });
});
