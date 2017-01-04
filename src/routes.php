<?php

Route::get('docs', function () {
    return view('apidoc::doc.index');
});

Route::get('api-doc/json', function () {
    $filePath = storage_path('appDoc/resource.json');
    
    return Response::make(File::get($filePath), 200,
        ['Content-Type' => 'application/json', 'Content-Length' => File::size($filePath)]);
});
