<?php

/*
|--------------------------------------------------------------------------
| Release metadata (Laboratory 8)
|--------------------------------------------------------------------------
|
| The version and revision identify the exact artifact that is running.
| They are baked into the build (VERSION file in Git, REVISION file written
| by the Docker build / release workflow) so the health check can prove that
| the running revision matches the approved release record.
|
| "lab_fault" is ONLY for the controlled faulty-release exercise. A faulty
| artifact is produced by building with LAB_FAULT=true, which writes the
| .lab-fault marker into the image. It disables the request form while using
| the same database schema, so the request-creation smoke test fails and the
| team can practise a rollback. Never build a real release with it.
|
*/

$readMarker = static function (string $file): ?string {
    $path = base_path($file);

    return is_file($path) ? trim((string) file_get_contents($path)) : null;
};

return [

    'version' => env('APP_VERSION') ?: ($readMarker('VERSION') ?: 'dev'),

    'revision' => env('APP_REVISION') ?: ($readMarker('REVISION') ?: 'unknown'),

    'built_at' => $readMarker('BUILD_DATE'),

    'lab_fault' => is_file(base_path('.lab-fault')),

];
