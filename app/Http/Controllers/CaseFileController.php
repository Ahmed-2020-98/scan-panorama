<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Support\FileResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as FileResponse;

class CaseFileController extends Controller
{
    public function __invoke(Request $request, CaseFile $file, string $mode): FileResponse
    {
        Gate::authorize('view', $file);

        if ($request->user()->isDoctor()) {
            $file->medicalCase->recordOpen();
        }

        return FileResponder::respond($file, $mode);
    }
}
